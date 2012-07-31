#include <string.h>
#include "types.h"
#include "utils.h"
#include "parse.h"
#include "manager.h"
#include "connections.h"
#include "io.h"
#include "str.h"
#include "bson_helpers.h"
#include "mini_bson.h"

#ifdef WIN32
#include <winsock2.h>
#  ifndef int64_t
     typedef __int64 int64_t;
#  endif
#else
#include <netinet/in.h>
#include <netinet/tcp.h>
#include <fcntl.h>
#include <netdb.h>
#include <sys/un.h>
#endif

#include <unistd.h>
#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <errno.h>
#include <sys/time.h>

#define INT_32  4
#define FLAGS   0

#define MONGO_REPLY_FLAG_QUERY_FAILURE 0x02

/* Helper functions */
inline int mongo_connection_get_reqid(mongo_connection *con)
{
	con->last_reqid++;
	return con->last_reqid;
}

static int mongo_util_connect__sockaddr(struct sockaddr *sa, int family, char *host, int port, char **errmsg)
{
#ifndef WIN32
	if (family == AF_UNIX) {
		struct sockaddr_un* su = (struct sockaddr_un*)(sa);
		su->sun_family = AF_UNIX;
		strncpy(su->sun_path, host, sizeof(su->sun_path));
	} else {
#endif
		struct hostent *hostinfo;
		struct sockaddr_in* si = (struct sockaddr_in*)(sa);

		si->sin_family = AF_INET;
		si->sin_port = htons(port);
		hostinfo = (struct hostent*)gethostbyname(host);

		if (hostinfo == NULL) {
			*errmsg = malloc(256);
			snprintf(*errmsg, 256, "Couldn't get host info for %s", host);
			return 0;
		}

#ifndef WIN32
		si->sin_addr = *((struct in_addr*)hostinfo->h_addr);
	}
#else
	si->sin_addr.s_addr = ((struct in_addr*)(hostinfo->h_addr))->s_addr;
#endif

	return 1;
}

/* This function does the actual connecting */
int mongo_connection_connect(char *host, int port, int timeout, char **error_message)
{
	struct sockaddr*   sa;
	struct sockaddr_in si;
	socklen_t          sn;
	int                family;
	struct timeval     tval;
	int                connected;
	int                status;
	int                tmp_socket;

	*error_message = NULL;

#ifdef WIN32
	WORD       version;
	WSADATA    wsaData;
	int        size, error;
	u_long     no = 0;
	const char yes = 1;
#else
	struct sockaddr_un su;
	uint               size;
	int                yes = 1;
#endif

#ifdef WIN32
	family = AF_INET;
	sa = (struct sockaddr*)(&si);
	sn = sizeof(si);

	version = MAKEWORD(2,2);
	error = WSAStartup(version, &wsaData);

	if (error != 0) {
		return -1;
	}

	/* create socket */
	tmp_socket = socket(family, SOCK_STREAM, 0);
	if (tmp_socket == INVALID_SOCKET) {
		*error_message = strdup(strerror(errno));
		return -1;
	}

#else
	/* domain socket */
	if (port == 0) {
		family = AF_UNIX;
		sa = (struct sockaddr*)(&su);
		sn = sizeof(su);
	} else {
		family = AF_INET;
		sa = (struct sockaddr*)(&si);
		sn = sizeof(si);
	}

	/* create socket */
	if ((tmp_socket = socket(family, SOCK_STREAM, 0)) == -1) {
		*error_message = strdup(strerror(errno));
		return -1;
	}
#endif

	/* TODO: Move this to within the loop & use real timeout setting */
	/* connection timeout: set in ms (current default 1000 secs) */
	tval.tv_sec = timeout <= 0 ? 1000 : timeout / 1000;
	tval.tv_usec = timeout <= 0 ? 0 : (timeout % 1000) * 1000;

	/* get addresses */
	if (mongo_util_connect__sockaddr(sa, family, host, port, error_message) == 0) {
		goto error;
	}

	setsockopt(tmp_socket, SOL_SOCKET, SO_KEEPALIVE, &yes, INT_32);
	setsockopt(tmp_socket, IPPROTO_TCP, TCP_NODELAY, &yes, INT_32);

#ifdef WIN32
	ioctlsocket(tmp_socket, FIONBIO, (u_long*)&yes);
#else
	fcntl(tmp_socket, F_SETFL, FLAGS|O_NONBLOCK);
#endif

	/* connect */
	status = connect(tmp_socket, sa, sn);
	if (status < 0) {
#ifdef WIN32
		errno = WSAGetLastError();
		if (errno != WSAEINPROGRESS && errno != WSAEWOULDBLOCK) {
#else
		if (errno != EINPROGRESS) {
#endif
			*error_message = strdup(strerror(errno));
			goto error;
		}

		while (1) {
			fd_set rset, wset, eset;

			FD_ZERO(&rset);
			FD_SET(tmp_socket, &rset);
			FD_ZERO(&wset);
			FD_SET(tmp_socket, &wset);
			FD_ZERO(&eset);
			FD_SET(tmp_socket, &eset);

			if (select(tmp_socket+1, &rset, &wset, &eset, &tval) == 0) {
				*error_message = malloc(256);
				snprintf(*error_message, 256, "Timed out after %d ms", timeout);
				goto error;
			}

			/* if our descriptor has an error */
			if (FD_ISSET(tmp_socket, &eset)) {
				*error_message = strdup(strerror(errno));
				goto error;
			}

			/* if our descriptor is ready break out */
			if (FD_ISSET(tmp_socket, &wset) || FD_ISSET(tmp_socket, &rset)) {
				break;
			}
		}

		size = sn;

		connected = getpeername(tmp_socket, sa, &size);
		if (connected == -1) {
			*error_message = strdup(strerror(errno));
			goto error;
		}
	}

	/* reset flags */
#ifdef WIN32
	ioctlsocket(tmp_socket, FIONBIO, &no);
#else
	fcntl(tmp_socket, F_SETFL, FLAGS);
#endif
	return tmp_socket;

error:
#ifdef WIN32
	shutdown((tmp_socket), 2);
	closesocket(tmp_socket);
	WSACleanup();
#else
	shutdown((tmp_socket), 2);
	close(tmp_socket);
#endif
	return -1;
}

mongo_connection *mongo_connection_create(mongo_con_manager *manager, mongo_server_def *server_def, char **error_message)
{
	mongo_connection *tmp;

	/* Init struct */
	tmp = malloc(sizeof(mongo_connection));
	memset(tmp, 0, sizeof(mongo_connection));
	tmp->last_reqid = rand();

	/* Connect */
	mongo_manager_log(manager, MLOG_CON, MLOG_INFO, "connection_create: creating new connection for %s:%d", server_def->host, server_def->port);
	tmp->socket = mongo_connection_connect(server_def->host, server_def->port, 1000, error_message);
	if (tmp->socket == -1) {
		mongo_manager_log(manager, MLOG_CON, MLOG_WARN, "connection_create: error: %s", *error_message);
		free(tmp);
		return NULL;
	}

	return tmp;
}

void mongo_connection_destroy(mongo_con_manager *manager, mongo_connection *con)
{
#ifdef WIN32
	shutdown(con->socket, SD_BOTH);
	closesocket(con->socket);
	WSACleanup();
#else
	shutdown(con->socket, SHUT_RDWR);
	close(con->socket);
#endif
	free(con->hash);
	free(con);
}

#define MONGO_REPLY_HEADER_SIZE 36

/**
 * Sends a ping command to the server and stores the result.
 *
 * Returns 1 when it worked, and 0 when an error was encountered.
 */
int mongo_connection_ping(mongo_con_manager *manager, mongo_connection *con)
{
	mcon_str      *packet;
	char          *error_message = NULL;
	int            read;
	struct timeval start, end;
	uint32_t       data_size;
	char           reply_buffer[MONGO_REPLY_HEADER_SIZE], *data_buffer;
	uint32_t       flags; /* To check for query reply status */

	mongo_manager_log(manager, MLOG_CON, MLOG_FINE, "is_ping: start");
	packet = bson_create_ping_packet(con);

	gettimeofday(&start, NULL);
	/* TODO: This is identical to mongo_connect_send_packet(), except for the gettimeofday()
	 * Is the ping time without the read overhead more accurate then just writing?
	 */

	/* Send and wait for reply */
	mongo_io_send(con->socket, packet->d, packet->l, &error_message);
	mcon_str_ptr_dtor(packet);
	read = mongo_io_recv_header(con->socket, reply_buffer, MONGO_REPLY_HEADER_SIZE, &error_message);

	/* If the header too small? */
	if (read < 5 * sizeof(int32_t)) {
		return 0;
	}
	/* Check for a query error */
	flags = MONGO_32(*(int*)(reply_buffer + sizeof(int32_t) * 4));
	if (flags & MONGO_REPLY_FLAG_QUERY_FAILURE) {
		return 0;
	}
	gettimeofday(&end, NULL);

	/* Read the rest of the data, which we'll ignore */
	data_size = MONGO_32(*(int*)(reply_buffer)) - MONGO_REPLY_HEADER_SIZE;
	mongo_manager_log(manager, MLOG_CON, MLOG_FINE, "is_ping: data_size: %d", data_size);
	/* TODO: Check size limits */
	data_buffer = malloc(data_size + 1);
	if (!mongo_io_recv_data(con->socket, data_buffer, data_size, &error_message)) {
		free(data_buffer);
		return 0;
	}
	free(data_buffer);

	con->last_ping = end.tv_sec;
	con->ping_ms = (end.tv_sec - start.tv_sec) * 1000 + (end.tv_usec - start.tv_usec) / 1000;
	if (con->ping_ms < 0) { /* some clocks do weird stuff */
		con->ping_ms = 0;
	}

	mongo_manager_log(manager, MLOG_CON, MLOG_WARN, "is_ping: last pinged at %ld; time: %dms", con->last_ping, con->ping_ms);

	return 1;
}

/**
 * Sends an is_master command to the server and returns an array of new connectable nodes
 *
 * Returns 1 when it worked, and 0 when an error was encountered.
 */
int mongo_connection_is_master(mongo_con_manager *manager, mongo_connection *con, char **repl_set_name, int *nr_hosts, char ***found_hosts, char **error_message)
{
	mcon_str      *packet;
	int32_t        max_bson_size = 0;
	char           *data_buffer;
	char          *set = NULL;      /* For replicaset in return */
	unsigned char  is_master = 0, arbiter = 0;
	char          *hosts, *ptr, *string;

	mongo_manager_log(manager, MLOG_CON, MLOG_FINE, "is_master: start");
	packet = bson_create_is_master_packet(con);

	if (!mongo_connect_send_packet(manager, con, packet, &data_buffer, error_message)) {
		return 0;
	}

	/* Find data fields */
	ptr = data_buffer + sizeof(int32_t); /* Skip the length */

	/* Do replica set name test */
	bson_find_field_as_string(ptr, "setName", &set);
	if (!set) {
		*error_message = strdup("Not a replicaset member");
		free(data_buffer);
		return 0;
	} else if (*repl_set_name) {
		if (strcmp(set, *repl_set_name) != 0) {
			struct mcon_str *tmp;

			mcon_str_ptr_init(tmp);
			mcon_str_add(tmp, "Host does not match replicaset name. Expected: ", 0);
			mcon_str_add(tmp, *repl_set_name, 0);
			mcon_str_add(tmp, "; Found: ", 0);
			mcon_str_add(tmp, set, 0);

			*error_message = strdup(tmp->d);
			mcon_str_ptr_dtor(tmp);

			free(data_buffer);
			return 0;
		} else {
			mongo_manager_log(manager, MLOG_CON, MLOG_FINE, "is_master: the found replicaset name matches the expected one (%s).", set);
		}
	} else if (*repl_set_name == NULL) {
		/* This can not happen, as for the REPLSET CON_TYPE to be active in the
		 * first place, there needs to be a repl_set_name set. */
		mongo_manager_log(manager, MLOG_CON, MLOG_WARN, "is_master: the replicaset name is not set, so we're using %s.", set);
		*repl_set_name = strdup(set);
	}

	/* Check for flags */
	bson_find_field_as_bool(ptr, "ismaster", &is_master);
	bson_find_field_as_bool(ptr, "arbiterOnly", &arbiter);

	/* Find max bson size */
	if (bson_find_field_as_int32(ptr, "maxBsonObjectSize", &max_bson_size)) {
		mongo_manager_log(manager, MLOG_CON, MLOG_INFO, "is_master: setting maxBsonObjectSize to %d", max_bson_size);
		con->max_bson_size = max_bson_size;
	}

	/* Find all hosts */
	bson_find_field_as_array(ptr, "hosts", &hosts);
	mongo_manager_log(manager, MLOG_CON, MLOG_FINE, "is_master: set name: %s, is_master: %d, is_arbiter: %d", set, is_master, arbiter);
	*nr_hosts = 0;
	ptr = hosts;
	while (bson_array_find_next_string(&ptr, &string)) {
		(*nr_hosts)++;
		*found_hosts = realloc(*found_hosts, (*nr_hosts) * sizeof(char*));
		(*found_hosts)[*nr_hosts-1] = strdup(string);
		mongo_manager_log(manager, MLOG_CON, MLOG_INFO, "found host: %s", string);
	}

	/* Set connection type depending on flags */
	if (is_master) {
		con->connection_type = MONGO_NODE_PRIMARY;
	} else if (arbiter) {
		con->connection_type = MONGO_NODE_ARBITER;
	} else {
		con->connection_type = MONGO_NODE_SECONDARY;
	} /* TODO: case for mongos */

	free(data_buffer);

	return 1;
}

/**
 * Sends an is_master command to the server to find server flags
 *
 * Returns 1 when it worked, and 0 when an error was encountered.
 */
int mongo_connection_get_server_flags(mongo_con_manager *manager, mongo_connection *con, char **error_message)
{
	mcon_str      *packet;
	int32_t        max_bson_size = 0;
	char           *data_buffer;
	char          *ptr;

	mongo_manager_log(manager, MLOG_CON, MLOG_FINE, "get_server_flags: start");
	packet = bson_create_is_master_packet(con);

	if (!mongo_connect_send_packet(manager, con, packet, &data_buffer, error_message)) {
		return 0;
	}

	/* Find data fields */
	ptr = data_buffer + sizeof(int32_t); /* Skip the length */

	/* Find max bson size */
	if (bson_find_field_as_int32(ptr, "maxBsonObjectSize", &max_bson_size)) {
		mongo_manager_log(manager, MLOG_CON, MLOG_INFO, "is_master: setting maxBsonObjectSize to %d", max_bson_size);
		con->max_bson_size = max_bson_size;
	} else {
		*error_message = strdup("Couldn't find the maxBsonObjectSize field");
		free(data_buffer);
		return 0;
	}

	free(data_buffer);

	return 1;
}

int mongo_connect_send_packet(mongo_con_manager *manager, mongo_connection *con, mcon_str *packet, char **data_buffer, char **error_message)
{
	int            read;
	uint32_t       data_size;
	char           reply_buffer[MONGO_REPLY_HEADER_SIZE];
	uint32_t       flags; /* To check for query reply status */

	/* Send and wait for reply */
	mongo_io_send(con->socket, packet->d, packet->l, error_message);
	mcon_str_ptr_dtor(packet);
	read = mongo_io_recv_header(con->socket, reply_buffer, MONGO_REPLY_HEADER_SIZE, error_message);

	mongo_manager_log(manager, MLOG_CON, MLOG_FINE, "send_packet: read from header: %d", read);
	if (read < MONGO_REPLY_HEADER_SIZE) {
		return 0;
	}

	/* Check for a query error */
	flags = MONGO_32(*(int*)(reply_buffer + sizeof(int32_t) * 4));
	if (flags & MONGO_REPLY_FLAG_QUERY_FAILURE) {
		return 0;
	}

	/* Read the rest of the data */
	data_size = MONGO_32(*(int*)(reply_buffer)) - MONGO_REPLY_HEADER_SIZE;
	mongo_manager_log(manager, MLOG_CON, MLOG_FINE, "send_packet: data_size: %d", data_size);

	/* TODO: Check size limits */
	*data_buffer = malloc(data_size + 1);
	if (!mongo_io_recv_data(con->socket, *data_buffer, data_size, error_message)) {
		free(data_buffer);
		return 0;
	}

	return 1;
}


