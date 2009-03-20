// bson.cpp
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
#include <mongo/client/dbclient.h>

#include "bson.h"
#include "mongo_types.h"

extern zend_class_entry *mongo_bindata_class;
extern zend_class_entry *mongo_code_class;
extern zend_class_entry *mongo_date_class;
extern zend_class_entry *mongo_id_class;
extern zend_class_entry *mongo_regex_class;

int php_array_to_bson( mongo::BSONObjBuilder *obj_builder, HashTable *arr_hash ) {
  zval **data;
  char *key;
  uint key_len;
  ulong index;
  zend_bool duplicate = 0;
  HashPosition pointer;
  int num = 0;

  if (zend_hash_num_elements(arr_hash) == 0) {
    return num;
  }

  for(zend_hash_internal_pointer_reset_ex(arr_hash, &pointer); 
      zend_hash_get_current_data_ex(arr_hash, (void**) &data, &pointer) == SUCCESS; 
      zend_hash_move_forward_ex(arr_hash, &pointer)) {

    // increment number of fields
    num++;

    int key_type = zend_hash_get_current_key_ex(arr_hash, &key, &key_len, &index, duplicate, &pointer);
    char *field_name;

    if (key_type == HASH_KEY_IS_STRING) {
      key_len = spprintf(&field_name, 0, "%s", key);
    }
    else if (key_type == HASH_KEY_IS_LONG) {
      key_len = spprintf(&field_name, 0, "%ld", index);
    }
    else {
      zend_error(E_WARNING, "key fail");
      continue;
    }

    add_to_bson(obj_builder, field_name, data);
    efree(field_name);
  }

  return num;
}

void bson_to_php_array(mongo::BSONObj *obj, zval *array) {
  mongo::BSONObjIterator it = mongo::BSONObjIterator(*obj);
  while (it.more()) {
    mongo::BSONElement elem = it.next();

    char *key = (char*)elem.fieldName();

    switch( elem.type() ) {
    case mongo::Undefined:
    case mongo::jstNULL: {
      add_assoc_null(array, key);
      break;
    }
    case mongo::NumberInt: {
      long num = (long)elem.number();
      add_assoc_long(array, key, num);
      break;
    }
    case mongo::NumberDouble: {
      double num = elem.number();
      add_assoc_double(array, key, num);
      break;
    }
    case mongo::Bool: {
      int b = elem.boolean();
      add_assoc_bool(array, key, b);
      break;
    }
    case mongo::String: {
      char *value = (char*)elem.valuestr();
      add_assoc_string(array, key, value, 1);
      break;
    }
    case mongo::Date: {
      zval *zdate = date_to_mongo_date(elem.date());
      add_assoc_zval(array, key, zdate);
      break;
    }
    case mongo::RegEx: {
      zval *zre = re_to_mongo_re((char*)elem.regex(), (char*)elem.regexFlags());
      add_assoc_zval(array, key, zre);
      break;
    }
    case mongo::BinData: {
      int size;
      char *bin = (char*)elem.binData(size);
      int type = elem.binDataType();
      zval *phpbin = bin_to_php_bin(bin, size, type);
      add_assoc_zval(array, key, phpbin);
      break;
    }
    case mongo::Code: {
      zval *empty;
      ALLOC_INIT_ZVAL(empty);
      array_init(empty);

      zval *zode = code_to_php_code(elem.valuestr(), empty);
      add_assoc_zval(array, key, zode);
      break;
    }
    case mongo::CodeWScope: {
      mongo::BSONObj bscope = elem.codeWScopeObject();
      zval *scope;

      ALLOC_INIT_ZVAL(scope);
      array_init(scope);
      bson_to_php_array(&bscope, scope);

      zval *zode = code_to_php_code(elem.codeWScopeCode(), scope);

      // get rid of extra reference to scope
      zval_ptr_dtor(&scope);

      add_assoc_zval(array, key, zode);
      break;
    }
    case mongo::Array:
    case mongo::Object: {
      mongo::BSONObj temp = elem.embeddedObject();
      zval *subarray;
      ALLOC_INIT_ZVAL(subarray);
      array_init(subarray);
      bson_to_php_array(&temp, subarray);
      add_assoc_zval(array, key, subarray);
      break;
    }
    case mongo::jstOID: {
      zval *zoid = oid_to_mongo_id(elem.__oid());
      add_assoc_zval(array, key, zoid);
      break;
    }
    case mongo::EOO: {
      break;
    }
    default:
      zend_error(E_WARNING, "bson=>php: type %i not supported\n", elem.type());
    }
  }
}

int prep_obj_for_db(mongo::BSONObjBuilder *obj_builder, HashTable *php_array) {
  zval **data;
  // check if "_id" field, 4 is length of "_id" + 1 for \0
  if (zend_hash_find(php_array, "_id", 4, (void**)&data) == FAILURE) {
    mongo::OID oid;
    oid.init();
    obj_builder->appendOID("_id", &oid);
    return 1;
  }
  add_to_bson(obj_builder, "_id", data);
  return 0;
}

int add_to_bson(mongo::BSONObjBuilder *obj_builder, char *field_name, zval **data) {
  switch (Z_TYPE_PP(data)) {
  case IS_NULL:
    obj_builder->appendNull(field_name);
    break;
  case IS_LONG:
    obj_builder->append(field_name, (int)Z_LVAL_PP(data));
    break;
  case IS_DOUBLE:
    obj_builder->append(field_name, Z_DVAL_PP(data));
    break;
  case IS_BOOL:
    obj_builder->append(field_name, Z_BVAL_PP(data));
    break;
  case IS_CONSTANT_ARRAY:
  case IS_ARRAY: {
    mongo::BSONObjBuilder subobj;
    php_array_to_bson(&subobj, Z_ARRVAL_PP(data));
    obj_builder->append(field_name, subobj.done());
    break;
  }
  case IS_STRING: {
    string s(Z_STRVAL_PP(data), Z_STRLEN_PP(data));
    obj_builder->append(field_name, s);
    break;
  }
  case IS_OBJECT: {
    TSRMLS_FETCH();
    zend_class_entry *clazz = Z_OBJCE_PP( data );
    /* check for defined classes */
    // MongoId
    if(clazz == mongo_id_class) {
      zval *zid = zend_read_property( mongo_id_class, *data, "id", 2, 0 TSRMLS_CC );
      char *cid = Z_STRVAL_P( zid );
      std::string id = string( cid );
      mongo::OID *oid = new mongo::OID();
      oid->init( id );

      obj_builder->appendOID( field_name, oid ); 
    }
    else if (clazz == mongo_date_class) {
      zval *zsec = zend_read_property( mongo_date_class, *data, "sec", 3, 0 TSRMLS_CC );
      long sec = Z_LVAL_P( zsec );
      zval *zusec = zend_read_property( mongo_date_class, *data, "usec", 4, 0 TSRMLS_CC );
      long usec = Z_LVAL_P( zusec );
      unsigned long long d = (unsigned long long)(sec * 1000) + (unsigned long long)(usec/1000);

      obj_builder->appendDate(field_name, d); 
    }
    else if (clazz == mongo_regex_class) {
      zval *zre = zend_read_property( mongo_regex_class, *data, "regex", 5, 0 TSRMLS_CC );
      char *re = Z_STRVAL_P( zre );
      zval *zflags = zend_read_property( mongo_regex_class, *data, "flags", 5, 0 TSRMLS_CC );
      char *flags = Z_STRVAL_P(zflags);

      obj_builder->appendRegex(field_name, re, flags); 
    }
    // MongoCode
    else if (clazz == mongo_code_class) {
      mongo::BSONObjBuilder scope_builder;

      zval *zcode = zend_read_property(mongo_code_class, *data, "code", 4, 0 TSRMLS_CC);
      char *code = Z_STRVAL_P( zcode );

      zval *zscope = zend_read_property(mongo_code_class, *data, "scope", 5, 0 TSRMLS_CC);
      php_array_to_bson(&scope_builder, Z_ARRVAL_P(zscope));

      // add code & scope to obj_builder
      obj_builder->appendCodeWScope(field_name, code, scope_builder.done()); 

    }
    else if (clazz == mongo_bindata_class) {
      zval *zbin = zend_read_property( mongo_bindata_class, *data, "bin", 3, 0 TSRMLS_CC );
      char *bin = Z_STRVAL_P( zbin );
      zval *zlen = zend_read_property( mongo_bindata_class, *data, "length", 6, 0 TSRMLS_CC );
      long len = Z_LVAL_P( zlen );
      zval *ztype = zend_read_property( mongo_bindata_class, *data, "type", 4, 0 TSRMLS_CC );
      long type = Z_LVAL_P( ztype );

      switch(type) {
      case 1:
        obj_builder->appendBinData(field_name, len, mongo::Function, bin); 
        break;
      case 3:
        obj_builder->appendBinData(field_name, len, mongo::bdtUUID, bin); 
        break;
      case 5:
        obj_builder->appendBinData(field_name, len, mongo::MD5Type, bin); 
        break;
      case 128:
        obj_builder->appendBinData(field_name, len, mongo::bdtCustom, bin); 
        break;
      default:
        obj_builder->appendBinData(field_name, len, mongo::ByteArray, bin); 
        break;
      }
    }
    break;
  }
  case IS_RESOURCE:
  case IS_CONSTANT:
  default:
    return FAILURE;
  }
  return SUCCESS;
}
