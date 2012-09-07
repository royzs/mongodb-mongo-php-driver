#include <string.h>

char *compat_strndup(const char *s, size_t len)
{
	char* ns = NULL;
	if (s) {
		ns = malloc(len + 1);
		if (ns) {
			ns[len] = 0;
			return strncpy(ns, s, len);
		}
	}
	return ns;
}
