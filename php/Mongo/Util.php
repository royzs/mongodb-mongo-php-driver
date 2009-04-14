<?php
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
 *
 * PHP version 5 
 *
 * @category Database
 * @package  Mongo
 * @author   Kristina Chodorow <kristina@10gen.com>
 * @license  http://www.apache.org/licenses/LICENSE-2.0  Apache License 2
 * @version  CVS: 000000
 * @link     http://www.mongodb.org
 */

/**
 * Handy methods for programming with this driver.
 * 
 * @category Database
 * @package  Mongo
 * @author   Kristina Chodorow <kristina@10gen.com>
 * @license  http://www.apache.org/licenses/LICENSE-2.0  Apache License 2
 * @link     http://www.mongodb.org
 */
class MongoUtil
{
  
    /**
     * Less than.
     */
    const LT = '$lt';

    /**
     * Less than or equal to.
     */
    const LTE = '$lte';

    /**
     * Greater than.
     */
    const GT = '$gt';

    /**
     * Greater than or equal to.
     */
    const GTE = '$gte';

    /**
     * Checks for a field in an object.
     */
    const IN = '$in';

    /**
     * Not equal.
     */
    const NE = '$ne';


    /**
     * Sort ascending.
     */
    const ASC = 1;

    /**
     * Sort descending.
     */
    const DESC = -1;


    /**
     * Function as binary data.
     */
    const BIN_FUNCTION = 1;

    /**
     * Default binary type: an arrray of binary data.
     */
    const BIN_ARRAY = 2;

    /**
     * Universal unique id.
     */
    const BIN_UUID = 3;

    /**
     * Binary MD5.
     */
    const BIN_MD5 = 5;

    /**
     * User-defined binary type.
     */
    const BIN_CUSTOM = 128;


    /* Command collection */
    protected static $CMD = '.$cmd';

    /* Admin database */
    const ADMIN = "admin";

    /* Commands */
    const AUTHENTICATE      = "authenticate";
    const CREATE_COLLECTION = "create";
    const DELETE_INDICES    = "deleteIndexes";
    const DROP              = "drop";
    const DROP_DATABASE     = "dropDatabase";
    const FORCE_ERROR       = "forceerror";
    const INDEX_INFO        = "cursorInfo";
    const LAST_ERROR        = "getlasterror";
    const LIST_DATABASES    = "listDatabases";
    const LOGGING           = "opLogging";
    const LOGOUT            = "logout";
    const NONCE             = "getnonce";
    const PREV_ERROR        = "getpreverror";
    const PROFILE           = "profile";
    const QUERY_TRACING     = "queryTraceLevel";
    const REPAIR_DATABASE   = "repairDatabase";
    const RESET_ERROR       = "reseterror";
    const SHUTDOWN          = "shutdown";
    const TRACING           = "traceAll";
    const VALIDATE          = "validate";


    /**
     * Turns something into an array that can be saved to the db.
     * Returns the empty array if passed null.
     *
     * @param any $obj object to convert
     *
     * @return array the array
     */
    public static function objToArray($obj) 
    {
        if (is_null($obj)) {
            return array();
        }

        $arr = array();
        foreach ($obj as $key => $value) {
            if (is_object($value) || is_array($value)) {
                $arr[ $key ] = MongoUtil::objToArray($value);
            } else {
                $arr[ $key ] = $value;
            }
        }
        return $arr;
    }

    /**
     * Converts a field or array of fields into an underscore-separated string.
     *
     * @param string|array $keys field(s) to convert
     *
     * @return string the index name
     */
    public static function toIndexString($keys) 
    {
        if (is_string($keys)) {
            $name = str_replace(".", "_", $keys) + "_1";
        } else if (is_array($keys) || is_object($keys)) {
            $key_list = array();
            foreach ($keys as $k=>$v) {
                $digit = $v == 1 ? 1 : -1;
                array_push($key_list, str_replace(".", "_", $k) . "_" . $digit);
            }
            $name = implode("_", $key_list);
        }
        return $name;
    }

    /** Execute a db command.
     *
     * @param resource $conn database connection
     * @param array    $data the query to send
     * @param string   $db   the database name
     *
     * @return array database response
     */
    public static function dbCommand($conn, $data, $db) 
    {
        $cmd_collection = $db . MongoUtil::$CMD;
        $cursor = mongo_query($conn, 
                              $cmd_collection, 
                              $data, 
                              0,
                              -1,
                              array());
        if(mongo_has_next($cursor)) {
            return mongo_next($cursor);
        }
        throw new MongoException("no database response");
        return null;
    }

    /**
     * Parse boolean configuration settings from php.ini.
     *
     * @param string $str the setting name
     *
     * @return bool the value of the setting
     */
    public static function getConfig($str) 
    {
        $setting = get_cfg_var($str);
        if (!$setting || strcasecmp($setting, "off") == 0) {
            return false;
        }
        return true;
    }
}

?>
