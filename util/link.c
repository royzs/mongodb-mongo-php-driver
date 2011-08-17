// link.c
/**
 *  Copyright 2009-2011 10gen, Inc.
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

#include <php.h>

#include "../php_mongo.h"
#include "link.h"
#include "rs.h"
#include "pool.h"
#include "connect.h"
#include "server.h"

extern zend_class_entry *mongo_ce_Mongo,
  *mongo_ce_ConnectionException;
ZEND_EXTERN_MODULE_GLOBALS(mongo);


mongo_server* mongo_util_link_get_slave_socket(mongo_link *link, zval *errmsg TSRMLS_DC) {
  int status;

  // sanity check
  if (!link->rs) {
    ZVAL_STRING(errmsg, "Connection is not a replica set", 1);
    return 0;
  }

  // see if we need to update hosts or ping them
  mongo_util_rs_ping(link TSRMLS_CC);

  if (link->slave) {
    if (link->slave->connected) {
      return link->slave;
    }

    if (mongo_util_pool_get(link->slave, errmsg TSRMLS_CC) == SUCCESS) {
      return link->slave;
    }

    // TODO: what if we can't reconnect?  close cursors? grab another slave?
  }

  status = mongo_util_rs__set_slave(link, &(Z_STRVAL_P(errmsg)) TSRMLS_CC);
  if (status == FAILURE) {
    ZVAL_STRING(errmsg, "Could not find any server to read from", 1);
    return 0;
  }

  return link->slave;
}

/*
 * If the socket is connected, returns the master.  If the socket is
 * disconnected, it attempts to reconnect and return the master.
 *
 * sets errmsg and returns 0 on failure
 */
mongo_server* mongo_util_link_get_socket(mongo_link *link, zval *errmsg TSRMLS_DC) {
  int connected = 0;
  mongo_server *server;

  if ((link->server_set->num == 1 && !link->rs && link->server_set->server->connected) ||
      (link->server_set->master && link->server_set->master->connected)) {
    connected = 1;
  }

  // errmsg is set, if neccessary, in get_master
  return mongo_util_link_get_master(link, errmsg TSRMLS_CC);
}

mongo_server* mongo_util_link_get_master(mongo_link *link, zval *errmsg TSRMLS_DC) {
  mongo_server *potential_master;

  if (link->rs) {
    potential_master = mongo_util_rs_get_master(link TSRMLS_CC);

    if (potential_master == 0) {
      ZVAL_STRING(errmsg, "Couldn't determine master", 1);
    }

    return potential_master;
  }

  // clear the error message
  Z_STRVAL_P(errmsg) = 0;

  // for a non-rs, go through the list of server until someone is connected
  potential_master = link->server_set->server;
  while (potential_master) {
    if (potential_master->connected || mongo_util_pool_get(potential_master, errmsg TSRMLS_CC) == SUCCESS) {
      return potential_master;
    }

    potential_master = potential_master->next;

    // keep the errmsg the last time through the loop
    if (potential_master && Z_STRVAL_P(errmsg)) {
      efree(Z_STRVAL_P(errmsg));
      Z_STRVAL_P(errmsg) = 0;
    }
  }

  return 0;
}

void mongo_util_link_master_failed(mongo_link *link TSRMLS_DC) {
  mongo_util_pool_failed(link->server_set->master TSRMLS_CC);
  mongo_util_server_down(link->server_set->master TSRMLS_CC);

  link->server_set->master = 0;
  link->slave = 0;

  mongo_util_rs_ping(link TSRMLS_CC);
}

void mongo_util_link_disconnect(mongo_link *link TSRMLS_DC) {
  mongo_server *current = link->server_set->server;

  while (current) {
    mongo_util_pool_close(current TSRMLS_CC);
    current = current->next;
  }

  link->server_set->master = 0;
}

