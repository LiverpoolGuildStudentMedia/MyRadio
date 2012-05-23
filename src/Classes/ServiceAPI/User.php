<?php
/**
 * The user object provides and stores information about a user
 * It is not a singleton for Impersonate purposes
 * @version 23052012
 * @author Lloyd Wallis <lpw@ury.york.ac.uk>
 */
class User {
  private $memberid;
  private $permissions;
  private $name;
  private $email;
  private $college;
  private $phone;
  private $receive_email;
  private $local_name;
  private $account_locked;
  private $db;
  
  private function __construct($memberid) {
    $this->initDB();
    $this->memberid = $memberid;
    //Get the base data
    $data = $this->db->fetch_one(
            'SELECT fname || sname AS name, college, phone, email,
              receive_email, local_name, account_locked FROM member
              WHERE memberid=$1 LIMIT 1',
            array($memberid));
    //Set the variables
    foreach ($data as $key => $value) $this->$key = $value;
    
    //Get the user's permissions
    $this->permissions = $this->db->fetch_column('SELECT lookupid FROM auth_officer
      WHERE officerid IN (SELECT officerid FROM member_officer
        WHERE memberid=$1 AND from_date < now()- interval \'1 month\' AND
        (till_date IS NULL OR till_date > now()- interval \'1 month\'))',
            array($memberid));
  }
  
  /**
   * Initialises the database instance 
   */
  private function initDB() {
    $this->db = Database::getInstance();
  }
  
  /**
   * Reestablishes the database connection after being Cached 
   */
  public function __wakeup() {
    $this->initDB();
  }
  
  /**
   * Returns the User's memberid
   * @return int The User's memberid
   */
  public function getID() {
    return $this->memberid;
  }
  
  /**
   * Returns if the user has the given permission
   * @param int $authid The permission to test for
   * @return boolean Whether this user has the requested permission 
   */
  public function hasAuth($authid) {
    return (in_array($authid, $this->permissions));
  }
  
  public static function getInstance($memberid = -1) {
    //Prepare a cache object
    $cache = Config::$cache_provider;
    $cache = $cache::getInstance();
    
    //Check the input is an int, and use the session user if not otherwise told
    $memberid = (int) $memberid;
    if ($memberid === -1) $memberid = $_SESSION['memberid'];
    
    //Return the object if it is cached
    $entry = $cache->get('MyURYUser_'.$memberid);
    if ($entry === false) {
      //Not cached.
      $entry = new User($memberid);
      $cache->set('MyURYUser_'.$memberid, $entry, 3600);
    } else {
      //Wake up the object
      $entry->__wakeup();
    }
    
    return $entry;
  }
}
