<?php

/**
 * This file provides the CoreUtils class for MyURY
 * @package MyURY_Core
 */

/**
 * Standard API Utilities. Basically miscellaneous functions for the core system
 * No database accessing etc should be setup here.
 *
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @version 20130709
 * @package MyURY_Core
 * @todo Factor out permission code into a seperate class?
 */
class CoreUtils {

  /**
   * This stores whether the Permissions have been defined to prevent re-defining, causing errors and wasting time
   * Once setUpAuth is run, this is set to true to prevent subsequent runs
   * @var boolean
   */
  private static $auth_cached = false;
  private static $svc_version_cache = array();
  private static $svc_id_cache = array();

  /**
   * Checks whether a given Module/Action combination is valid
   * @param String $module The module to check
   * @param String $action The action to check. Default 'default'
   * @return boolean Whether or not the request is valid
   * @assert ('Core', 'default') === true
   * @assert ('foo', 'barthatdoesnotandwillnoteverexisteverbecauseitwouldbesilly') === false
   * @assert ('../foo', 'bar') === false
   * @assert ('foo', '../bar') === false
   */
  public static function isValidController($module, $action = null) {
    if ($action === null)
      $action = Config::$default_action;
    try {
      self::actionSafe($action);
      self::actionSafe($module);
    } catch (MyURYException $e) {
      return false;
    }
    /**
     * This is better than file_exists because it ensures that the response is valid for a version which has the file
     * when live does not
     */
    return is_string(stream_resolve_include_path('Controllers/' . $module . '/' . $action . '.php'));
  }

  /**
   * Provides a template engine object compliant with TemplateEngine interface
   * @return URYTwig 
   * @todo Make this generalisable for drop-in template engine replacements
   * @assert () !== false
   * @assert () !== null
   */
  public static function getTemplateObject() {
    require_once 'Twig/Autoloader.php';
    Twig_Autoloader::register();
    require_once 'Classes/URYTwig.php';
    return new URYTwig();
  }

  /**
   * Checks whether a requested action is safe
   * @param String $action A module action
   * @return boolean Whether the module is safe to be used on a filesystem
   * @throws MyURYException Thrown if directory traversal detected
   * @assert ('safe!') === true
   * @assert ('../notsafe!') throws MyURYException
   */
  public static function actionSafe($action) {
    if (strpos($action, '/') !== false) {
      //Someone is trying to traverse directories
      throw new MyURYException('Directory Traversal Thrwated');
      return false;
    }
    return true;
  }

  /**
   * Formats pretty much anything into a happy, human readable date/time
   * @param string $timestring Some form of time
   * @param bool $time Whether to include Hours,Mins. Default yes
   * @return String A happy time 
   * @assert (40000) == '01/01/1970'
   */
  public static function happyTime($timestring, $time = true, $date = true) {
    return date(($date ? 'd/m/Y' : '') . ($time && $date ? ' ' : '') . ($time ? 'H:i' : ''), is_numeric($timestring) ? $timestring : strtotime($timestring));
  }

  /**
   * Formats a number into h:m:s format.
   * @param int $int
   * @return String
   */
  public static function intToTime($int) {
    $hours = floor($int / 3600);
    if ($hours === 0) $hours = null; else $hours = $hours.':';
    
    $mins = floor(($int - ($hours * 3600)) / 60);
    $secs = ($int - ($hours * 3600) - ($mins * 60));
    return "$hours$mins:$secs";
  }

  /**
   * Returns a postgresql-formatted timestamp
   * @param int $time The time to get the timestamp for. Default right now.
   * @return String a timestamp
   * @assert (30) == '1970-01-01 00:00:30'
   */
  public static function getTimestamp($time = null) {
    if ($time === null)
      $time = time();

    return date('Y-m-d H:i:s', $time);
  }

  /**
   * Gives you the starting year of the current academic year
   * @return int year
   * @assert () == 2012
   */
  public static function getAcademicYear() {
    if (date('m') >= 9)
      return (int) date('Y');
    else
      return (int) date('Y') - 1;
  }

  /**
   * Returns a postgresql formatted interval
   * @param int $start The start time
   * @param int $end The end time
   * @return String a PgSQL valid interval value
   * @assert (0, 0) == '0 seconds'
   */
  public static function makeInterval($start, $end) {
    return $end - $start . ' seconds';
  }

  /**
   * Builds a module/action URL
   * @param string $module
   * @param string $action
   * @param array $params Additional GET variables
   * @return String URL to Module/Action
   */
  public static function makeURL($module, $action = null, $params = array()) {
    //Check if there is a custom URL configured
    if ($action !== null && class_exists('Database')) {
      $result = Database::getInstance()->fetch_one('SELECT custom_uri FROM myury.actions
        WHERE actionid=$1', array(self::getActionId(self::getModuleId($module),$action)));
      if (!empty($result[0])) return $result[0];
    }
    
    if (Config::$rewrite_url) {
      $str = Config::$base_url . $module . '/' . (($action !== null) ? $action . '/' : '');
      if (!empty($params)) {
        if (is_string($params)) {
          if (substr($params,0,1) !== '?') $str .= '?';
          $str .= $params;
        } else {
          $str .= '?';
          foreach ($params as $k => $v) {
            $str .= "$k=$v&";
          }
          $str = substr($str, 0, -1);
        }
      }
    } else {
      $str = Config::$base_url . '?module=' . $module . (($action !== null) ? '&action=' . $action : '');

      if (!empty($params)) {
        if (is_string($params)) {
          $str .= $params;
        } else {
          foreach ($params as $k => $v) {
            $str .= "&$k=$v";
          }
        }
      }
    }
    return $str;
  }

  /**
   * Sets up the Authentication Constants
   * @return void
   * @assert () == null
   */
  public static function setUpAuth() {
    if (self::$auth_cached)
      return;

    $db = Database::getInstance();
    $result = $db->fetch_all('SELECT typeid, phpconstant FROM l_action');
    foreach ($result as $row) {
      define($row['phpconstant'], $row['typeid']);
    }

    self::$auth_cached = true;
  }

  /**
   * Checks using cached Shibbobleh permissions whether the current member has the specified permission
   * @param int $permission The ID of the permission, resolved by using an AUTH_ constant
   * @return boolean Whether the member has the requested permission
   * @todo this is a duplication of the stuff in the User class. deprecate?
   * @deprecated
   */
  public static function hasPermission($permission) {
    if (!isset($_SESSION['member_permissions']))
      return false;
    return in_array($permission, $_SESSION['member_permissions']);
  }

  /**
   * Checks if the user has the given permission
   * @param int $permission A permission constant to check
   * @return void Will Fatal error if the user does not have the permission
   */
  public static function requirePermission($permission) {
    if (!self::hasPermission($permission)) {
      //Load the 403 controller and exit
      require 'Controllers/Errors/403.php';
      exit;
    }
  }

  /**
   * Checks if the user has the given permissions required for the given Module/Action combination
   * 
   * The query needs a little bit of explaining.<br>
   * The first two WHERE clauses just set up foreign key references - we're searching by name, not ID.<br>
   * The next two WHERE clauses return exact or wildcard matches for this Module/Action combination.<br>
   * The final two AND NOT phrases make sure it ignores wildcards that allow any access.
   * 
   * @param String $module The Module to check permissions for
   * @param String $action The Action to check permissions for
   * @param bool $require If true, will die if the user does not have permission. If false, will just return false
   * @return bool True on required or authorised, false on unauthorised
   */
  public static function requirePermissionAuto($module, $action, $require = true) {
    self::setUpAuth();
    $db = Database::getInstance();
    /**
     * 
     */
    $result = $db->fetch_column('SELECT typeid FROM myury.act_permission
      LEFT OUTER JOIN myury.modules ON act_permission.moduleid=modules.moduleid
      LEFT OUTER JOIN myury.actions ON act_permission.actionid=actions.actionid
      AND (myury.modules.name=$1 OR myury.act_permission.moduleid IS NULL)
      AND (myury.actions.name=$2 OR myury.act_permission.actionid IS NULL)
      AND NOT (myury.act_permission.actionid IS NULL AND myury.act_permission.typeid IS NULL)
      AND NOT (myury.act_permission.moduleid IS NULL AND myury.act_permission.typeid IS NULL)', array($module, $action));

    //Don't allow empty result sets - throw an Exception as this is very very bad.
    if (empty($result)) {
      throw new MyURYException('There are no permissions defined for the ' . $module . '/' . $action . ' action!');
      return false;
    }

    $authorised = false;
    foreach ($result as $permission) {
      //It only needs to match one
      if ($permission === null || self::hasPermission($permission)) {
        $authorised = true;
        break;
      }
    }

    if (!$authorised && $require) {
      //Fatal error
      require 'Controllers/Errors/403.php';
      exit;
    }

    //Return true on required success, or whether authorised otherwise
    return $require || $authorised;
  }

  /**
   * Returns a list of all currently defined permissions on MyURY Service/Module/Action combinations.
   *
   * This has multiple UNIONS with similar queries so it gracefully deals with NULL values - the joins lose them.
   * 
   * @todo Is there a nicer way of doing this?
   * @todo Won't do null fields. Requires outer joins.
   * 
   * @return Array A 2D Array, where each second dimensions is as follows:<br>
   * action: The name of the Action page<br>
   * module: The name of the Module the action is in<br>
   * service: The name of the Service the module is in<br>
   * permission: The name of the permission applied to that Service/Module/Action combination<br>
   * actpermissionid: The unique ID of this Service/Module/Action combination
   * 
   */
  public static function getAllActionPermissions() {
    return Database::getInstance()->fetch_all(
                    'SELECT actpermissionid,
          myury.services.name AS service,
          myury.modules.name AS module,
          myury.actions.name AS action,
          public.l_action.descr AS permission
          FROM myury.act_permission, myury.services, myury.modules, myury.actions, public.l_action
        WHERE myury.act_permission.actionid=myury.actions.actionid
        AND myury.act_permission.moduleid=myury.modules.moduleid
        AND myury.act_permission.serviceid=myury.services.serviceid
        AND myury.act_permission.typeid = public.l_action.typeid
        
        UNION
        
        SELECT actpermissionid,
          myury.services.name AS service,
          myury.modules.name AS module,
          \'ALL ACTIONS\' AS action,
          public.l_action.descr AS permission
          FROM myury.act_permission, myury.services, myury.modules, public.l_action
        WHERE myury.act_permission.moduleid=myury.modules.moduleid
        AND myury.act_permission.serviceid=myury.services.serviceid
        AND myury.act_permission.typeid = public.l_action.typeid
        AND myury.act_permission.actionid IS NULL
        
        UNION
        
        SELECT actpermissionid,
          myury.services.name AS service,
          myury.modules.name AS module,
          myury.actions.name AS action,
          \'GLOBAL ACCESS\' AS permission
          FROM myury.act_permission, myury.services, myury.modules, myury.actions
        WHERE myury.act_permission.moduleid=myury.modules.moduleid
        AND myury.act_permission.serviceid=myury.services.serviceid
        AND myury.act_permission.actionid=myury.actions.actionid
        AND myury.act_permission.typeid IS NULL
        
        ORDER BY service, module');
  }

  /**
   * Returns a list of Permissions ready for direct use in a select MyURYFormField
   * @return Array A 2D Array matching the MyURYFormField::TYPE_SELECT specification.
   */
  public static function getAllPermissions() {
    return Database::getInstance()->fetch_all('SELECT typeid AS value, descr AS text FROM public.l_action
      ORDER BY descr ASC');
  }

  /**
   * Returns a list of all MyURY managed Services in a 2D Array.
   * @return Array A 2D Array with each second dimension as follows:<br>
   * value: The ID of the Service
   * text: The Text ID of the Service
   * enabeld: Whether the Service is enabled
   */
  public static function getServices() {
    return Database::getInstance()->fetch_all('SELECT serviceid AS value, name AS text, enabled
      FROM myury.services ORDER BY name ASC');
  }

  /**
   * A simple debug method that only displays output for a specific user.
   * @param int $userid The ID of the user to display for
   * @param String $message The HTML to display for this user
   * @assert (7449, 'Test') == null
   */
  public static function debug_for($userid, $message) {
    if ($_SESSION['memberid'] == $userid)
      echo '<p>' . $message . '</p>';
  }

  /**
   * Returns the ID of a Module, creating it if necessary
   * @todo Document this
   * @param type $module
   * @return type
   */
  public static function getModuleId($module) {
    $db = Database::getInstance();
    $result = $db->fetch_column('SELECT moduleid FROM myury.modules WHERE name=$1', array($module));

    if (empty($result)) {
      //The module needs creating
      $result = $db->fetch_column('INSERT INTO myury.modules (serviceid, name) VALUES ($1, $2) RETURNING moduleid', array(Config::$service_id, $module));
    }
    return $result[0];
  }

  /**
   * Returns the ID of a Service/Module/Action request, creating it if necessary
   * @param int $module
   * @param string $action
   * @return type
   */
  public static function getActionId($module, $action) {
    $db = Database::getInstance();
    $result = $db->fetch_column('SELECT actionid FROM myury.actions WHERE moduleid=$1 AND name=$2', array($module, $action));

    if (empty($result)) {
      //The module needs creating
      $result = $db->fetch_column('INSERT INTO myury.actions (moduleid, name) VALUES ($1, $2) RETURNING actionid', array($module, $action));
    }
    return $result[0];
  }

  /**
   * Assigns a permission to a command
   * @todo Document
   * @param type $module
   * @param type $action
   * @param type $permission
   */
  public static function addActionPermission($module, $action, $permission) {
    $db = Database::getInstance();
    $db->query('INSERT INTO myury.act_permission (serviceid, moduleid, actionid, typeid)
      VALUES ($1, $2, $3, $4)', array(Config::$service_id, $module, $action, $permission));
  }

  /**
   * @todo Document this
   * @param User $user
   */
  public static function getServiceVersionForUser(User $user) {
    $serviceid = Config::$service_id;
    $key = $serviceid . '-' . $user->getID();

    if (!isset(self::$svc_version_cache[$key])) {
      $db = Database::getInstance();

      if ($user->getID() === User::getInstance()->getID()) {
        //It's the current user. If they have an override defined in their session, use that.
        if (isset($_SESSION['myury_svc_version_' . $serviceid])) {
          return array(
              'version' => $_SESSION['myury_svc_version_' . $serviceid],
              'path' => $_SESSION['myury_svc_version_' . $serviceid . '_path']
          );
        }
      }

      $result = $db->fetch_one('SELECT version, path FROM myury.services_versions
      WHERE serviceid IN (SELECT serviceid FROM myury.services_versions_member
        WHERE memberid=$2 AND serviceversionid IN (SELECT serviceversionid FROM myury.services_versions
          WHERE serviceid=$1)
         )', array($serviceid, $user->getID()));

      if (empty($result)) {
        self::$svc_version_cache[$key] = self::getDefaultServiceVersion();
      } else {
        self::$svc_version_cache[$key] = $result;
      }
    }
    return self::$svc_version_cache[$key];
  }

  /**
   * @todo Document this.
   * @return boolean
   */
  public static function getDefaultServiceVersion() {
    $db = Database::getInstance();

    return $db->fetch_one('SELECT version, path FROM myury.services_versions WHERE serviceid=$1 AND is_default=true
      LIMIT 1', array(Config::$service_id));
  }

  /**
   * @todo Document this.
   * @return boolean
   */
  public static function getServiceVersions() {
    $db = Database::getInstance();

    return $db->fetch_all('SELECT version, path FROM myury.services_versions WHERE serviceid=$1', array(Config::$service_id));
  }

  /**
   * Parses an object or array into client array datasource
   * @param mixed $data
   * @return array
   */
  public static function dataSourceParser($data) {
    if (is_object($data) && $data instanceof MyURY_DataSource) {
      return $data->toDataSource();
    } elseif (is_array($data)) {
      foreach ($data as $k => $v) {
        $data[$k] = self::dataSourceParser($v);
      }
      return $data;
    } else {
      return $data;
    }
  }

  //from http://www.php.net/manual/en/function.xml-parse-into-struct.php#109032
  public static function xml2array($xml) {
    $opened = array();
    $opened[1] = 0;
    $xml_parser = xml_parser_create();
    xml_parse_into_struct($xml_parser, $xml, $xmlarray);
    $array = array_shift($xmlarray);
    unset($array["level"]);
    unset($array["type"]);
    $arrsize = sizeof($xmlarray);
    for ($j = 0; $j < $arrsize; $j++) {
      $val = $xmlarray[$j];
      switch ($val["type"]) {
        case "open":
          $opened[$val["level"]] = 0;
        case "complete":
          $index = "";
          for ($i = 1; $i < ($val["level"]); $i++)
            $index .= "[" . $opened[$i] . "]";
          $path = explode('][', substr($index, 1, -1));
          $value = &$array;
          foreach ($path as $segment)
            $value = &$value[$segment];
          $value = $val;
          unset($value["level"]);
          unset($value["type"]);
          if ($val["type"] == "complete")
            $opened[$val["level"] - 1]++;
          break;
        case "close":
          $opened[$val["level"] - 1]++;
          unset($opened[$val["level"]]);
          break;
      }
    }
    return $array;
  }

  public static function requireTimeslot() {
    if (!isset($_SESSION['timeslotid'])) {
      header('Location: ' . Config::$shib_url . '/timeslot.php?next=' . $_SERVER['REQUEST_URI']);
      exit;
    }
  }
  
  public static function backWithMessage($message) {
    header('Location: '.$_SERVER['HTTP_REFERER'] . (strstr($_SERVER['HTTP_REFERER'], '?') !== false ? '&' : '?') . 'message='.base64_encode($message));
  }
  
  /**
   * Returns a randomly selected item from the list, in a biased manner
   * Weighted should be an integer - how many times to put the item into the bag
   * @param Array $data 2D of Format [['item' => mixed, 'weight' => n], ...]
   */
  public static function biased_random($data) {
    $bag = array();
    
    foreach ($data as $ball) {
      for (;$ball['weight'] > 0; $ball['weight']--) {
        $bag[] = $ball['item'];
      }
    }
    
    return $bag[array_rand($bag)];
  }

}