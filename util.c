// util.c
/**
 *  Copyright 2009 10gen, Inc.
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

#include "mongo.h"

extern zend_class_entry *spl_ce_InvalidArgumentException;

extern int le_connection,
  le_pconnection;


zend_class_entry *mongo_util_ce = NULL;

static char* MONGO_CMD = "$cmd";


static char *get_cmd_ns(char *db, int db_len) {
  char *position;

  char *cmd_ns = (char*)emalloc(db_len + strlen(MONGO_CMD) + 2);
  position = cmd_ns;

  // db
  memcpy(position, db, db_len);
  position += db_len;

  // .
  *(position)++ = '.';

  // $cmd
  memcpy(position, MONGO_CMD, strlen(MONGO_CMD));
  position += strlen(MONGO_CMD);

  // \0
  *(position) = '\0';

  return cmd_ns;
}

static char *replace_dots(char *key, int key_len, char *position) {
  int i;
  for (i=0; i<key_len; i++) {
    if (key[i] == '.') {
      *(position)++ = '_';
    }
    else {
      *(position)++ = key[i];
    }
  }
  return position;
}

/* {{{ MongoUtil::toIndexString(array|string) */
PHP_METHOD(MongoUtil, toIndexString) {
  zval **zkeys;
  int param_count = 1;
  char *name, *position;
  int len = 0;

  if (ZEND_NUM_ARGS() != param_count) {
    ZEND_WRONG_PARAM_COUNT();
  }

  zend_get_parameters_ex(param_count, &zkeys);
  if (Z_TYPE_PP(zkeys) == IS_ARRAY) {
    HashTable *hindex = Z_ARRVAL_PP(zkeys);
    HashPosition pointer;
    zval **data;
    char *key;
    int key_len, first = 1;
    ulong index;

    for(zend_hash_internal_pointer_reset_ex(hindex, &pointer); 
        zend_hash_get_current_data_ex(hindex, (void**)&data, &pointer) == SUCCESS; 
        zend_hash_move_forward_ex(hindex, &pointer)) {

      int key_type = zend_hash_get_current_key_ex(hindex, &key, &key_len, &index, NO_DUP, &pointer);
      switch (key_type) {
      case HASH_KEY_IS_STRING: {
        len += key_len;

        convert_to_long(*data);
        len += Z_LVAL_PP(data) == 1 ? 2 : 3;

        break;
      }
      case HASH_KEY_IS_LONG:
        convert_to_string(*data);

        len += Z_STRLEN_PP(data);
        len += 2;
        break;
      default:
        continue;
      }
    }

    name = (char*)emalloc(len+1);
    position = name;

    for(zend_hash_internal_pointer_reset_ex(hindex, &pointer); 
        zend_hash_get_current_data_ex(hindex, (void**)&data, &pointer) == SUCCESS; 
        zend_hash_move_forward_ex(hindex, &pointer)) {

      if (!first) {
        *(position)++ = '_';
      }
      first = 0;

      int key_type = zend_hash_get_current_key_ex(hindex, &key, &key_len, &index, NO_DUP, &pointer);

      if (key_type == HASH_KEY_IS_LONG) {
        key_len = spprintf(&key, 0, "%ld", index);
        key_len += 1;
      }

      // copy str, replacing '.' with '_'
      position = replace_dots(key, key_len-1, position);
      
      *(position)++ = '_';
      
      convert_to_long(*data);
      if (Z_LVAL_PP(data) != 1) {
        *(position)++ = '-';
      }
      *(position)++ = '1';

      if (key_type == HASH_KEY_IS_LONG) {
        efree(key);
      }
    }
    *(position) = 0;
  }
  else {
    convert_to_string(*zkeys);

    int len = Z_STRLEN_PP(zkeys);

    name = (char*)emalloc(len + 3);
    position = name;
 
    // copy str, replacing '.' with '_'
    position = replace_dots(Z_STRVAL_PP(zkeys), Z_STRLEN_PP(zkeys), position);

    *(position)++ = '_';
    *(position)++ = '1';
    *(position) = '\0';
  }
  RETURN_STRING(name, 0)
}

PHP_METHOD(MongoUtil, dbCommand) {
  mongo_link *link;
  char *db;
  int db_len;
  int param_count = 3;

  zval **zlink, **zdata, **zdb;

  if (ZEND_NUM_ARGS() != param_count) {
    ZEND_WRONG_PARAM_COUNT();
  }

  zend_get_parameters_ex(param_count, &zlink, &zdata, &zdb);
  if (Z_TYPE_PP(zlink) == IS_RESOURCE &&
      Z_TYPE_PP(zdata) == IS_ARRAY &&
      Z_TYPE_PP(zdb)  == IS_STRING) {
    ZEND_FETCH_RESOURCE2(link, mongo_link*, zlink, -1, PHP_CONNECTION_RES_NAME, le_connection, le_pconnection); 
    db = Z_STRVAL_PP(zdb);
    db_len = Z_STRLEN_PP(zdb);
  }
  else {
    zend_throw_exception(spl_ce_InvalidArgumentException, "expected MongoUtil::dbCommand(resource, array, string)", 0 TSRMLS_CC);
    return;
  }

  // create db.$cmd
  char *cmd_ns = get_cmd_ns(db, db_len);

  // query
  mongo_cursor *cursor = mongo_do_query(link, cmd_ns, 0, -1, *zdata, 0 TSRMLS_CC);
  efree(cmd_ns);

  // return 1 result
  zval *next = mongo_do_next(cursor TSRMLS_CC);
  RETURN_ZVAL(next, 0, 1);
}


static function_entry MongoUtil_methods[] = {
  PHP_ME(MongoUtil, toIndexString, NULL, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
  PHP_ME(MongoUtil, dbCommand, NULL, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
};


void mongo_init_MongoUtil(TSRMLS_D) {
  zend_class_entry ce;

  INIT_CLASS_ENTRY(ce, "MongoUtil", MongoUtil_methods);
  mongo_util_ce = zend_register_internal_class(&ce TSRMLS_CC);


  zend_declare_class_constant_string(mongo_util_ce, "LT", strlen("LT"), "$lt" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "LTE", strlen("LTE"), "$lte" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "GT", strlen("GT"), "$gt" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "GTE", strlen("GTE"), "$gte" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "IN", strlen("IN"), "$in" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "NE", strlen("NE"), "$ne" TSRMLS_CC);

  zend_declare_class_constant_long(mongo_util_ce, "ASC", strlen("ASC"), 1 TSRMLS_CC);
  zend_declare_class_constant_long(mongo_util_ce, "DESC", strlen("DESC"), -1 TSRMLS_CC);

  zend_declare_class_constant_long(mongo_util_ce, "BIN_FUNCTION", strlen("BIN_FUNCTION"), 1 TSRMLS_CC);
  zend_declare_class_constant_long(mongo_util_ce, "BIN_ARRAY", strlen("BIN_ARRAY"), 2 TSRMLS_CC);
  zend_declare_class_constant_long(mongo_util_ce, "BIN_UUID", strlen("BIN_UUID"), 3 TSRMLS_CC);
  zend_declare_class_constant_long(mongo_util_ce, "BIN_MD5", strlen("BIN_MD5"), 5 TSRMLS_CC);
  zend_declare_class_constant_long(mongo_util_ce, "BIN_CUSTOM", strlen("BIN_CUSTOM"), 128 TSRMLS_CC);

  zend_declare_class_constant_string(mongo_util_ce, "ADMIN", strlen("ADMIN"), "admin" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "AUTHENTICATE", strlen("AUTHENTICATE"), "authenticate" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "CREATE_COLLECTION", strlen("CREATE_COLLECTION"), "create" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "DELETE_INDICES", strlen("DELETE_INDICES"), "deleteIndexes" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "DROP", strlen("DROP"), "drop" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "DROP_DATABASE", strlen("DROP_DATABASE"), "dropDatabase" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "FORCE_ERROR", strlen("FORCE_ERROR"), "forceerror" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "INDEX_INFO", strlen("INDEX_INFO"), "cursorInfo" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "LAST_ERROR", strlen("LAST_ERROR"), "getlasterror" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "LIST_DATABASES", strlen("LIST_DATABASES"), "listDatabases" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "LOGGING", strlen("LOGGING"), "opLogging" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "LOGOUT", strlen("LOGOUT"), "logout" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "NONCE", strlen("NONCE"), "getnonce" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "PREV_ERROR", strlen("PREV_ERROR"), "getpreverror" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "PROFILE", strlen("PROFILE"), "profile" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "QUERY_TRACING", strlen("QUERY_TRACING"), "queryTraceLevel" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "REPAIR_DATABASE", strlen("REPAIR_DATABASE"), "repairDatabase" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "RESET_ERROR", strlen("RESET_ERROR"), "reseterror" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "SHUTDOWN", strlen("SHUTDOWN"), "shutdown" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "TRACING", strlen("TRACING"), "traceAll" TSRMLS_CC);
  zend_declare_class_constant_string(mongo_util_ce, "VALIDATE", strlen("VALIDATE"), "validate" TSRMLS_CC);
}
