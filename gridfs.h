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

PHP_FUNCTION( mongo_gridfs_init );
PHP_FUNCTION( mongo_gridfs_list );
PHP_FUNCTION( mongo_gridfs_store );
PHP_FUNCTION( mongo_gridfs_find );

PHP_FUNCTION( mongo_gridfile_filename );
PHP_FUNCTION( mongo_gridfile_size );
PHP_FUNCTION( mongo_gridfile_write );

#include "mongo.h"

#define DEFAULT_CHUNK_SIZE (256*1024)

typedef struct mongo_gridfs {
  mongo_link *link;

  char *db;
  int db_len;
  char *prefix;

  char *file_ns;
  char *chunk_ns;
};
