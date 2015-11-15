<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 11/11/2015
 * Time: 17:28
 */
namespace bbn\api;

/**
 * Virtualmin API
 *
 * @author Edwin Mugendi <edwinmugendi@gmail.com>
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 *
 */
class virtualmin {

  private
    /** @var  Virtualmin username */
    $user,
    /** @var  Virtualmin password */
    $pass,
    /** @var  Virtualmin hostname */
    $hostname;

  public
    // The last action to have been performed
    $last_action = false,
    $error = false,
    $message;

  /**
   * This is the default construtor
   */
  public function __construct($config) {
    $this->user = $config['user'];
    $this->pass = $config['pass'];
    $this->hostname = $config['hostname'];
  }

//End of default constructor
  /**
   * This function is used to get the header url part to be executed
   * @return string The the header url part to be executed
   */
  private function get_header_url() {
    return "wget -O - --quiet --http-user=" . $this->user . " --http-passwd=" . $this->pass . " --no-check-certificate 'https://" . $this->hostname . ":10000/virtual-server/remote.cgi?json=1&program=";
  }

//End of get_header_url() function

  /**
   * API DESCRIPTION: Create a mail, FTP or database user
   * This function is used to create a mail, FTP or database user
   * @param array the parameter to create an new user
   * @return array $result_minimal,
   * $result_minimal['status'] the status of the execution, it can be 'success' meaning that the user has been successfully created, or 'failure' meaning that the user was not created
   * $result_minimal['message'] the corresponding success or failure message of the shell_exec
   */
  public function create_user($param) {
    //Prepping, processing and validating the create user parameters
    $param = $this->process_parameters($param);
    //Setting the last action performed
    $this->last_action = "create-user";
    //Defining  the $url_part and the command to be executed
    $url_part = "create-user";
    if (isset($param['domain'], $param['user'])) {
      //Converting the MB quota to KBs
      $param['quota'] = ((int) $param['quota'] * 1024);

      $url_part .= "&domain=" . $param['domain'] . "&user=" . $param['user'];
    }//End of if statement

    if (isset($param['pass'])) {//pass parameter is set
      $url_part .= "&pass=" . $param['pass'];
    } else if (isset($param['encpass'])) {//encpass parameter is set
      $url_part .= "&encpass=" . $param['encpass'];
    } else if (isset($param['random-pass'])) {//random-pass parameter is set
      $url_part .= "&random-pass";
    } else if (isset($param['passfile'])) {//passfile parameter is set
      $url_part .= "&passfile=" . $param['passfile'];
    }//End of if else statement
    //Setting the optional options
    if (isset($param['quota'])) {//quota is set
      //Converting the MB quota to KBs
      $param['quota'] = ((int) $param['quota'] * 1024);

      $url_part .= "&quota=" . $param['quota'];
    }//End of if statement

    if (isset($param['realname'])) {//Real name is set
      $url_part .= "&real=" . $param['realname'];
    }//End of if statement

    if (isset($param['ftp'])) {//ftp is set
      $url_part .= "&ftp";
    }//End of if statement

    if (isset($param['shell'])) {//shell is set
      $url_part .= "&shell" . $param['shell'];
    }//End of if statement

    if (isset($param['noemail'])) {//noemail is set
      $url_part .= "&noemail";
    }//End of if statement

    if (isset($param['extra'])) {//extra is set
      $url_part .= "&extra" . $param['extra'];
    }//End of if statement

    if (isset($param['mysql']) && is_array($param['mysql'])) {//mysql parameter is set and is an array
      foreach ($param['mysql'] as $single_mysql_db) {//Iterate of over the mysql db array and concatenate it to $url_part
        $url_part .= "&mysql=" . $single_mysql_db;
      }//End of foreach statement
    }
    if (isset($param['group']) && is_array($param['group'])) {//group parameter is set and is an array
      foreach ($param['group'] as $single_group) {//Iterate of over the group db array and concatenate it to $url_part
        $url_part .= "&group=" . $single_group;
      }//End of foreach statement
    }

    if (isset($param['web'])) {//web is set
      $url_part .= "&web";
    }//End of if statement

    if (isset($param['no-check-spam'])) {//no-check-spam is set
      $url_part .= "&no-check-spam";
    }//End of if statement

    if (isset($param['no-creation-mail'])) {//no-creation-mail is set
      $url_part .= "&no-creation-mail";
    }//End of if statement

    if (isset($param['home'])) {//home is set
      $url_part .= "&home=".$param['home'];
    }//End of if statement

    //Concatenating the closing single quote
    $url_part .="'";
    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->get_header_url() . $url_part;
    //Calling shell_exec and returning the result array
    return $this->call_shell_exec($url_part);
  }

//End of create_user() function

  /**
   * API DESCRIPTION: Temporarily disable a virtual server.
   * This function is used to temporarily diable a virtual server
   * @param array the parameter to delete a virtual server
   * @subarray none
   * @return array $result_minimal,
   * $result_minimal['status'] the status of the execution, it can be 'success' meaning that the domain has been successfully disabled, or 'failure' meaning that the domain has not been disabled
   * $result_minimal['message'] the corresponding success or failure message of the shell_exec
   */
  public function disable_domain($param) {
    //Prepping, processing and validating the create user parameters
    $param = $this->process_parameters($param);
    //Setting the last action performed
    $this->last_action = "disable-domain";

    //Defining  the $url_part and the command to be executed
    $url_part = "disable-domain";

    if (isset($param['domain'])) {//domain parameter is set
      $url_part .= "&domain=" . $param['domain'];
    }//End of if statement

    if (isset($param['why'])) {//why parameter is set
      $url_part .= "&why=\"" . $param['why'] . "\"";
    }//End of if else statement
    //Concatenating the closing single quote
    $url_part .="'";
    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->get_header_url() . $url_part;
    //Calling shell_exec and returning the result array
    return $this->call_shell_exec($url_part);
  }

//End of disable_domain() function

  /**
   * API DESCRIPTION: Re-enable one virtual server.
   * This function is used to re-enable one virtual server
   * @param array the parameter to re-enable a virtual server
   * @subarray none
   * @return array $result_minimal,
   * $result_minimal['status'] the status of the execution, it can be 'success' meaning that the domain has been successfully enabled, or 'failure' meaning that the domain has not been enabled
   * $result_minimal['message'] the corresponding success or failure message of the shell_exec
   */
  public function enable_domain($param) {
    //Prepping, processing and validating the create user parameters
    $param = $this->process_parameters($param);
    //Setting the last action performed
    $this->last_action = "enable-domain";

    //Defining  the $url_part and the command to be executed
    $url_part = "enable-domain";

    if (isset($param['domain'])) {//domain parameter is set
      $url_part .= "&domain=" . $param['domain'];
    }//End of if statement
    //Concatenating the closing single quote
    $url_part .="'";
    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->get_header_url() . $url_part;
    //Calling shell_exec and returning the result array
    return $this->call_shell_exec($url_part);
  }

//End of disable_domain() function

  /**
   * API DESCRIPTION: Delete one or more virtual servers.
   * This function is used to delete one or more virtual servers
   * @param array the parameter to delete one virtual server
   * @subarray domain,user
   * @return array $result_minimal,
   * $result_minimal['status'] the status of the execution, it can be 'success' meaning that the domain has been successfully deleted, or 'failure' meaning that the domain has not been deleted
   * $result_minimal['message'] the corresponding success or failure message of the shell_exec
   */
  public function delete_domain($param) {
    //Prepping, processing and validating the create user parameters
    $param = $this->process_parameters($param);

    //Setting the last action performed
    $this->last_action = "delete-domain";

    //Defining  the $url_part and the command to be executed
    $url_part = "delete-domain";

    if (isset($param['domain']) && is_array($param['domain'])) {//domain parameter is set and is an array
      foreach ($param['domain'] as $single_domain) {//Iterate of over the domain array and concatenate it to $url_part
        $url_part .= "&domain=" . $single_domain;
      }//End of foreach statement
    } else if (isset($param['user']) && is_array($param['user'])) {//user parameter is set and is an array
      foreach ($param['user'] as $single_user) {//Iterate of over the user array and concatenate it to $url_part
        $url_part .= "&user=" . $single_user;
      }//End of foreach statement
    } else if (isset($param['only'])) {//only parameter is set
      $url_part .="&only";
    }//End of if else statement
    //Concatenating the closing single quote
    $url_part .="'";
    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->get_header_url() . $url_part;
    //Calling shell_exec and returning the result array
    return $this->call_shell_exec($url_part);
  }

//End of delete_domain() function

  /**
   * API DESCRIPTION: Lists all virtual servers.
   * This function is used to list all virtual servers
   * @param array the parameter to delete one virtual server
   * @subarray domain,user
   * @return array $result_minimal,
   * $result_minimal['status'] the status of the execution, it can be 'success' meaning that the domain has been successfully deleted, or 'failure' meaning that the domain has not been deleted
   * $result_minimal['message'] the corresponding success or failure message of the shell_exec
   */
  public function list_domains($param = array('name-only' => 1)) {
    //Prepping, processing and validating the create user parameters
    $param = $this->process_parameters($param);

    //Setting the last action performed
    $this->last_action = "list-domains";

    //Defining  the $url_part and the command to be executed
    $url_part = "list-domains";

    if (isset($param['multiline'])) {//multiline parameter is set
      $url_part .= "&multiline";
    } else if (isset($param['name-only'])) {//name-only parameter is set
      $url_part .= "&name-only";
    } else if (isset($param['id-only'])) {//id-only parameter is set
      $url_part .= "&id-only";
    } else if (isset($param['simple-multiline'])) {//simple-multiline parameter is set
      $url_part .= "&simple-multiline";
    } else if (isset($param['user-only'])) {//user-only parameter is set
      $url_part .= "&user-only";
    } else if (isset($param['home-only'])) {//home-only parameter is set
      $url_part .= "&home-only";
    } //End of if else statement

    if (isset($param['domain']) && is_array($param['domain'])) {//domain parameter is set and is an array
      foreach ($param['domain'] as $single_domain) {//Iterate of over the domain array and concatenate it to $url_part
        $url_part .= "&domain=" . $single_domain;
      }//End of foreach statement
    }//End of if statement
    if (isset($param['user']) && is_array($param['user'])) {//user parameter is set and is an array
      foreach ($param['user'] as $single_user) {//Iterate of over the user array and concatenate it to $url_part
        $url_part .= "&user=" . $single_user;
      }//End of foreach statement
    }//End of if statement

    if (isset($param['with-feature'])) {//with-feature parameter is set
      $url_part .= "&with-feature=" . $param['with-feature'];
    } //End of if statement

    if (isset($param['without-feature'])) {//without-feature parameter is set
      $url_part .= "&without-feature=" . $param['without-feature'];
    } //End of if statement

    if (isset($param['alias'])) {//alias  parameter is set
      $url_part .= "&alias";
    } else if (isset($param['no-alias'])) {//no-alias  parameter is set
      $url_part .= "&no-alias";
    } else if (isset($param['subserver'])) {//subserver parameter is set
      $url_part .= "&subserver";
    } else if (isset($param['toplevel'])) {//toplevel parameter is set
      $url_part .= "&toplevel";
    } else if (isset($param['subdomain'])) {//subdomain parameter is set
      $url_part .= "&subdomain";
    }//End of if else statement

    if (isset($param['plan'])) {//plan parameter is set
      $url_part .= "&plan=" . $param['plan'];
    } //End of if statement

    if (isset($param['reseller'])) {//reseller parameter is set
      $url_part .= "&reseller=" . $param['reseller'];
    } else if (isset($param['no-reseller'])) {//no-reseller  parameter is set
      $url_part .= "&no-reseller";
    } else if (isset($param['any-reseller'])) {//any-reseller  parameter is set
      $url_part .= "&any-reseller";
    }//End of if else statement

    if (isset($param['id']) && is_array($param['id'])) {//id parameter is set and is an array
      foreach ($param['id'] as $single_id) {//Iterate of over the id array and concatenate it to $url_part
        $url_part .= "&id=" . $single_id;
      }//End of foreach statement
    }//End of if statement
    //Concatenating the closing single quote
    $url_part .="'";
    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->get_header_url() . $url_part;
    //Calling shell_exec and returning the result array
    return $this->call_shell_exec($url_part);
  }

//End of delete_domain() function

  /**
   * API DESCRIPTION: Turn on some features for a virtual server
   * This function is used to turn on some features for a virtual server
   * @param array the parameter to turn on some features for a virtual server
   * @subarray none
   * @return array $result_minimal,
   * $result_minimal['status'] the status of the execution, it can be 'success' meaning that the domain features has been successfully enabled, or 'failure' meaning that the domain features have not been enabled
   * $result_minimal['message'] the corresponding success or failure message of the shell_exec
   */
  public function enable_feature($param) {
    //Prepping, processing and validating the create user parameters
    $param = $this->process_parameters($param);

    //Setting the last action performed
    $this->last_action = "enable-feature";

    //Defining  the $url_part and the command to be executed
    $url_part = "enable-feature";

    if (isset($param['domain'])) {//domain parameter is set
      $url_part .= "&domain=" . $param['domain'];
    } else if (isset($param['user'])) {//user parameter is set
      $url_part .= "&user=" . $param['user'];
    } else if (isset($param['all-domains'])) {
      $url_part .="&all-domains";
    }//End of if else statement

    if (isset($param['unix'])) {//unix parameter is set
      $url_part .= "&unix";
    }//End of if statement

    if (isset($param['dir'])) {//dir parameter is set
      $url_part .= "&dir";
    }//End of if statement

    if (isset($param['dns'])) {//dns parameter is set
      $url_part .= "&dns";
    }//End of if statement

    if (isset($param['mail'])) {//mail parameter is set
      $url_part .= "&mail";
    }//End of if statement

    if (isset($param['web'])) {//web parameter is set
      $url_part .= "&web";
    }//End of if statement

    if (isset($param['webalizer'])) {//webalizer parameter is set
      $url_part .= "&webalizer";
    }//End of if statement

    if (isset($param['ssl'])) {//ssl parameter is set
      $url_part .= "&ssl";
    }//End of if statement

    if (isset($param['logrotate'])) {//logrotate parameter is set
      $url_part .= "&logrotate";
    }//End of if statement

    if (isset($param['mysql'])) {//mysql parameter is set
      $url_part .= "&mysql";
    }//End of if statement

    if (isset($param['ftp'])) {//ftp parameter is set
      $url_part .= "&ftp";
    }//End of if statement

    if (isset($param['spam'])) {//spam parameter is set
      $url_part .= "&spam";
    }//End of if statement

    if (isset($param['virus'])) {//virus parameter is set
      $url_part .= "&virus";
    }//End of if statement

    if (isset($param['status'])) {//status parameter is set
      $url_part .= "&status";
    }//End of if statement

    if (isset($param['webmin'])) {//webmin parameter is set
      $url_part .= "&webmin";
    }//End of if statement

    if (isset($param['virtualmin-awstats'])) {//virtualmin-awstats parameter is set
      $url_part .= "&virtualmin-awstats";
    }//End of if statement

    if (isset($param['virtualmin-dav'])) {//virtualmin-dav parameter is set
      $url_part .= "&virtualmin-dav";
    }//End of if statement

    if (isset($param['virtualmin-svn'])) {//virtualmin-svn parameter is set
      $url_part .= "&virtualmin-svn";
    }//End of if statement

    if (isset($param['skip-warnings'])) {//skip-warnings parameter is set
      $url_part .= "&skip-warnings";
    }//End of if statement
    //Concatenating the closing single quote
    $url_part .="'";
    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->get_header_url() . $url_part;
    //Calling shell_exec and returning the result array
    return $this->call_shell_exec($url_part);
  }

//End of enable_feature() function

  /**
   * API DESCRIPTION: Turn off some features for a virtual server
   * This function is used to turn off some features for a virtual server
   * @param array the parameter to turn off some features for a virtual server
   * @subarray none
   * @return array $result_minimal,
   * $result_minimal['status'] the status of the execution, it can be 'success' meaning that the domain features has been successfully diabled, or 'failure' meaning that the domain features have not been disabled
   * $result_minimal['message'] the corresponding success or failure message of the shell_exec
   */
  public function disable_feature($param) {
    //Prepping, processing and validating the create user parameters
    $param = $this->process_parameters($param);

    //Setting the last action performed
    $this->last_action = "diable-feature";

    //Defining  the $url_part and the command to be executed
    $url_part = "diable-feature";

    if (isset($param['domain'])) {//domain parameter is set
      $url_part .= "&domain=" . $param['domain'];
    } else if (isset($param['user'])) {//user parameter is set
      $url_part .= "&user=" . $param['user'];
    } else if (isset($param['all-domains'])) {
      $url_part .="&all-domains";
    }//End of if else statement

    if (isset($param['unix'])) {//unix parameter is set
      $url_part .= "&unix";
    }//End of if statement

    if (isset($param['dir'])) {//dir parameter is set
      $url_part .= "&dir";
    }//End of if statement

    if (isset($param['dns'])) {//dns parameter is set
      $url_part .= "&dns";
    }//End of if statement

    if (isset($param['mail'])) {//mail parameter is set
      $url_part .= "&mail";
    }//End of if statement

    if (isset($param['web'])) {//web parameter is set
      $url_part .= "&web";
    }//End of if statement

    if (isset($param['webalizer'])) {//webalizer parameter is set
      $url_part .= "&webalizer";
    }//End of if statement

    if (isset($param['ssl'])) {//ssl parameter is set
      $url_part .= "&ssl";
    }//End of if statement

    if (isset($param['logrotate'])) {//logrotate parameter is set
      $url_part .= "&logrotate";
    }//End of if statement

    if (isset($param['mysql'])) {//mysql parameter is set
      $url_part .= "&mysql";
    }//End of if statement

    if (isset($param['ftp'])) {//ftp parameter is set
      $url_part .= "&ftp";
    }//End of if statement

    if (isset($param['spam'])) {//spam parameter is set
      $url_part .= "&spam";
    }//End of if statement

    if (isset($param['virus'])) {//virus parameter is set
      $url_part .= "&virus";
    }//End of if statement

    if (isset($param['status'])) {//status parameter is set
      $url_part .= "&status";
    }//End of if statement

    if (isset($param['webmin'])) {//webmin parameter is set
      $url_part .= "&webmin";
    }//End of if statement

    if (isset($param['virtualmin-awstats'])) {//virtualmin-awstats parameter is set
      $url_part .= "&virtualmin-awstats";
    }//End of if statement

    if (isset($param['virtualmin-dav'])) {//virtualmin-dav parameter is set
      $url_part .= "&virtualmin-dav";
    }//End of if statement

    if (isset($param['virtualmin-svn'])) {//virtualmin-svn parameter is set
      $url_part .= "&virtualmin-svn";
    }//End of if statement
    //Concatenating the closing single quote
    $url_part .="'";
    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->get_header_url() . $url_part;
    //Calling shell_exec and returning the result array
    return $this->call_shell_exec($url_part);
  }

//End of enable_feature() function

  /**
   *  API DESCRIPTION: Create a virtual server
   * This function is used to create a virtual server
   * @param array the parameter to create an virtual server
   * @subarray file-name
   * @return array $result_minimal,
   * $result_minimal['status'] the status of the execution, it can be 'success' meaning that the domain has been successfully created, or 'failure' meaning that the domain was not created
   * $result_minimal['message'] the corresponding success or failure message of the shell_exec
   */
  public function create_domain($param = array('unix' => 1, 'dir' => 1, 'dns' => 1, 'web' => 1, 'logrotate' => 1, 'mail' => 1, 'spam' => 1, 'virus' => 1)) {
    //Prepping, processing and validating the create user parameters
    $param = $this->process_parameters($param);

    //Setting the last action performed
    $this->last_action = "create-domain";

//Defining  the $url_part and the command to be executed
    $url_part = "create-domain";

    if (isset($param['domain'])) {//Domain is set
      $url_part .= "&domain=" . $param['domain'];
    }//End of if statement

    if (isset($param['pass'])) {//pass parameter is set
      $url_part .= "&pass=" . $param['pass'];
    } else if (isset($param['passfile'])) {//passfile parameter is set
      $url_part .= "&passfile=" . $param['passfile'];
    }//End of if else statement

    if (isset($param['parent'])) {//parent parameter is set
      $url_part .= "&parent=" . $param['parent'];
    } else if (isset($param['alias'])) {//alias parameter is set
      $url_part .= "&alias =" . $param['alias '];
    } else if (isset($param['superdom'])) {//superdom parameter is set
      $url_part .= "&superdom=" . $param['superdom'];
    }//End of if else statement

    if (isset($param['desc'])) {//desc parameter is set
      $url_part .= "&desc=" . $param['desc'];
    }//End of if statement

    if (isset($param['email'])) {//email parameter is set
      $url_part .= "&email=" . $param['email'];
    }//End of if statement

    if (isset($param['user'])) {//user parameter is set
      $url_part .= "&user=" . $param['user'];
    }//End of if statement

    if (isset($param['group'])) {//group parameter is set
      $url_part .= "&group=" . $param['group'];
    }//End of if statement

    if (isset($param['unix'])) {//unix parameter is set
      $url_part .= "&unix";
    }//End of if statement

    if (isset($param['dir'])) {//dir parameter is set
      $url_part .= "&dir";
    }//End of if statement

    if (isset($param['dir'])) {//dir parameter is set
      $url_part .= "&dir";
    }//End of if statement

    if (isset($param['dns'])) {//dns parameter is set
      $url_part .= "&dns";
    }//End of if statement

    if (isset($param['mail'])) {//mail parameter is set
      $url_part .= "&mail";
    }//End of if statement

    if (isset($param['web'])) {//web parameter is set
      $url_part .= "&web";
    }//End of if statement

    if (isset($param['webalizer'])) {//webalizer parameter is set
      $url_part .= "&webalizer";
    }//End of if statement

    if (isset($param['ssl'])) {//ssl parameter is set
      $url_part .= "&ssl";
    }//End of if statement

    if (isset($param['logrotate'])) {//logrotate parameter is set
      $url_part .= "&logrotate";
    }//End of if statement

    if (isset($param['mysql'])) {//mysql parameter is set
      $url_part .= "&mysql";
    }//End of if statement

    if (isset($param['ftp'])) {//ftp parameter is set
      $url_part .= "&ftp";
    }//End of if statement

    if (isset($param['spam'])) {//spam parameter is set
      $url_part .= "&spam";
    }//End of if statement

    if (isset($param['virus'])) {//virus parameter is set
      $url_part .= "&virus";
    }//End of if statement

    if (isset($param['status'])) {//status parameter is set
      $url_part .= "&status";
    }//End of if statement

    if (isset($param['webmin'])) {//webmin parameter is set
      $url_part .= "&webmin";
    }//End of if statement

    if (isset($param['virtualmin-awstats'])) {//virtualmin-awstats parameter is set
      $url_part .= "&virtualmin-awstats";
    }//End of if statement

    if (isset($param['virtualmin-dav'])) {//virtualmin-dav parameter is set
      $url_part .= "&virtualmin-dav";
    }//End of if statement

    if (isset($param['virtualmin-svn'])) {//virtualmin-svn parameter is set
      $url_part .= "&virtualmin-svn";
    }//End of if statement

    if (isset($param['default-features'])) {//default-features parameter is set
      $url_part .= "&default-features";
    } else if (isset($param['features-from-plan'])) {//features-from-plan parameter is set
      $url_part .= "&features-from-plan";
    }//End of if else statement

    if (isset($param['allocate-ip'])) {//allocate-ip parameter is set
      $url_part .= "&allocate-ip";
    } else if (isset($param['ip'])) {//ip parameter is set
      $url_part .= "&ip=" . $param['ip'];
    } else if (isset($param['shared-ip'])) {//shared-ip parameter is set
      $url_part .= "&shared-ip=" . $param['shared-ip'];
    }//End of if else statement

    if (isset($param['ip-already'])) {//ip-already parameter is set
      $url_part .= "&ip-already";
    }//End of if statement

    if (isset($param['allocate-ip6'])) {//allocate-ip6 parameter is set
      $url_part .= "&allocate-ip6";
    } else if (isset($param['ip6'])) {//ip6 parameter is set
      $url_part .= "&ip6=" . $param['ip6'];
    }//End of if else statement

    if (isset($param['ip6-already'])) {//ip6-already parameter is set
      $url_part .= "&ip6-already";
    }//End of if statement

    if (isset($param['max-doms'])) {//max-doms parameter is set
      $url_part .= "&max-doms=" . $param['max-doms'];
    }//End of if statement

    if (isset($param['dns-ip'])) {//dns-ip parameter is set
      $url_part .= "&dns-ip=" . $param['dns-ip'];
    } else if (isset($param['no-dns-ip'])) {//no-dns-ip parameter is set
      $url_part .= "&no-dns-ip";
    }//End of if else statement

    if (isset($param['webmin'])) {//webmin parameter is set
      $url_part .= "&webmin";
    }//End of if statement

    if (isset($param['max-aliasdoms'])) {//max-aliasdoms parameter is set
      $url_part .= "&max-aliasdoms=" . $param['max-aliasdoms'];
    }//End of if statement

    if (isset($param['max-realdoms'])) {//max-realdoms parameter is set
      $url_part .= "&max-realdoms=" . $param['max-realdoms'];
    }//End of if statement

    if (isset($param['max-mailboxes'])) {//max-mailboxes parameter is set
      $url_part .= "&max-mailboxes=" . $param['max-mailboxes'];
    }//End of if statement

    if (isset($param['max-dbs'])) {//max-dbs parameter is set
      $url_part .= "&max-dbs=" . $param['max-dbs'];
    }//End of if statement

    if (isset($param['max-aliases'])) {//max-aliases parameter is set
      $url_part .= "&=max-aliases" . $param['max-aliases'];
    }//End of if statement

    if (isset($param['quota'])) {//quota parameter is set
      //Converting the MB quota to KBs
      $param['quota'] = ((int) $param['quota'] * 1024);

      $url_part .= "&=quota" . $param['quota'];
    }//End of if statement

    if (isset($param['uquota'])) {//uquota parameter is set
      //Converting the MB quota to KBs
      $param['uquota'] = ((int) $param['uquota'] * 1024);

      $url_part .= "&uquota=" . $param['uquota'];
    }//End of if statement

    if (isset($param['template'])) {//template parameter is set
      $url_part .= "&template=\"" . $param['template'] . "\"";
    }//End of if statement

    if (isset($param['plan'])) {//plan parameter is set
      $url_part .= "&plan=\"" . $param['plan'] . "\"";
    }//End of if statement

    if (isset($param['limits-from-plan'])) {//limits-from-plan parameter is set
      $url_part .= "&limits-from-plan";
    }//End of if statement

    if (isset($param['prefix'])) {//prefix parameter is set
      $url_part .= "&prefix=" . $param['prefix'];
    }//End of if statement

    if (isset($param['db'])) {//db parameter is set
      $url_part .= "&db=" . $param['db'];
    }//End of if statement

    if (isset($param['fwdto'])) {//fwdto parameter is set
      $url_part .= "&fwdto=" . $param['fwdto'];
    }//End of if statement

    if (isset($param['reseller'])) {//reseller parameter is set
      $url_part .= "&reseller=" . $param['reseller'];
    }//End of if statement

    if (isset($param['style'])) {//style parameter is set
      $url_part .= "&style=" . $param['style'];
    }//End of if statement

    if (isset($param['content'])) {//content parameter is set
      $url_part .= "&content=" . $param['content'];
    }//End of if statement

    if (isset($param['mysql-pass'])) {//mysql-pass parameter is set
      $url_part .= "&mysql-pass=" . $param['mysql-pass'];
    }//End of if statement

    if (isset($param['skip-warnings'])) {//skip-warnings parameter is set
      $url_part .= "&skip-warnings";
    }//End of if statement

    if (isset($param['field-name']) && is_array($param['field-name'])) {//field-name parameter is set and is an array
      foreach ($param['field-name'] as $single_field_name) {//Iterate of over the field name array and concatenate it to $url_part
        $url_part .= "&field-name=" . $single_field_name;
      }//End of foreach statement
    }//End of if statement
    //Concatenating the closing single quote
    $url_part .="'";
    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->get_header_url() . $url_part;
    //Calling shell_exec and returning the result array
    return $this->call_shell_exec($url_part);
  }

//End of create_domain() function

  /**
   * This function is used to execute the $request using shell_exec
   * @param string $request the command to be excecuted
   * @return array an array with the execution status and message
   */
  private function call_shell_exec($request) {
//Executing the shell_exec, decoding the json result into an array
    $result_array = json_decode(shell_exec($request), TRUE);
    //Array to be returned
    $result_minimal = array();

    //Setting the status status into the the $result_minimal array
    $result_minimal['status'] = $result_array['status'];
    //Setting the command that was executed into the $result_minimal array
    $result_minimal['command'] = $result_array['command'];

    if ($result_array['status'] == 'success' && substr($this->last_action, 0, 5) == 'list-') {//shell_exec call successed and the last_action is a list- command
      //Setting the data message
      $result_minimal['message'] = $result_array['data'];
    } else if ($result_array['status'] == 'success') {//shell_exec call successed
      //Setting the success message
      $result_minimal['message'] = $result_array['output'];
    } else {//shell_exec call failed
      //Setting the error message
      $this->error = $result_array['error'];
      return false;
    }//End of inner if else statement
    //Returning the status and message array
    return $result_minimal;
  }

//End of call_shell_exec() function

  /**
   * This function is used to process the parameters
   * @param array $param the raw parameters
   * @return array the processed parameters
   */
  private function process_parameters($param) {
    foreach ($param as $key => $val) {
      if (is_array($val)) {//$val is an array
        //Variable to watch the number of parameter values in sub array
        $param_count = 0;
        foreach ($val as $single_val) {
          $param[$key][$param_count] = \bbn\cls\str\text::encode_filename_virtalmin($single_val);
          $param_count++;
        }//End of inner foreach statement
      } else {//$val is not an array
        $param[$key] = \bbn\cls\str\text::encode_filename_virtalmin($val);
      }//End of inner if else statement
    }//End of outer foreach statement
    //Return the processed parameters
    return $param;
  }

//End of process_parameters() function

  public function list_commands($param = array('multiline' => 1)) {
    //Prepping, processing and validating the create user parameters
    $param = $this->process_parameters($param);
    //Setting the last action performed
    $this->last_action = "list-commands";

    //Defining  the $url_part and the command to be executed
    $url_part = "list-commands";
    if (isset($param['short'])) {//short parameter is set
      $url_part .= "&short";
    }//End of if statement

    if (isset($param['multiline'])) {//multiline parameter is set
      $url_part .= "&multiline";
    } else if (isset($param['nameonly'])) {//nameonly parameter is set
      $url_part .= "&nameonly";
    }//End of if else statement
    //Concatenating the closing single quote
    $url_part .="'";
    //Concatenating the header url and $url_part to create the full url to be executed
    $url_part = $this->get_header_url() . $url_part;
    //Calling shell_exec and returning the result array
    return $this->call_shell_exec($url_part);
  }

//End of process_parameters() function
}



/*
$config = array(
  'user' => 'root',
  'pass' => 'JvLy3HbJ',
  'hostname' => 'localhost'
);
//Creating a virtualmin object
$vm = new virtualmin($config);
$list_commands_param = array(
  'multiline' => 1
);
//$vm->list_commands($list_commands_param);
if ($r = $vm->list_domains()) {
  print_r($r);
} else {
  var_dump($vm->error);
}
$create_domain_param = array(
  'domain' => 'fr1s2.co',
  'pass' => 'mugendi',
  'dns' => 1,
  'web' => 1,
  'mail' => 1
);
echo '</pre><p>create domain with mail: </p><pre>';
//if ($r = $vm->create_domain($create_domain_param)) {
//    print_r($r);
//} else {
//   var_dump($vm->error);
//}
$create_user_param = array(
  'domain' => 'fr1s.co',
  'user' => 'edwin61',
  'pass' => 'mugendi',
  'quota' => 20,
  'realname' => 'Edwin Mugendi'
);
echo '<p>create user: </p><pre>';
if ($r = $vm->create_user($create_user_param)) {
  print_r($r);
} else {
  var_dump($vm->error);
}
echo '</pre><p>repeat create user: </p><pre>';
if ($r = $vm->create_user($create_user_param)) {
  print_r($a);
} else {
  var_dump($vm->error);
}
$create_domain_param = array(
  'domain' => 'edw661.co',
  'pass' => 'mugendi',
  'dns' => 1,
  'web' => 1
  );
  echo '</pre><p>create domain: </p><pre>';
  print_r($vm->create_domain($create_domain_param));
  $create_domain_param = array(
  'domain' => 'edw4441.co',
  'pass' => 'mugendi',
  'dns' => 1,
  'web' => 1
  );
  echo '</pre><p>create domain: </p><pre>';
  print_r($vm->create_domain($create_domain_param));
  $create_domain_param = array(
  'domain' => 'edw155.co',
  'pass' => 'mugendi',
  'dns' => 1,
  'web' => 1
  );
  echo '</pre><p>repeat create domain: </p><pre>';
  print_r($vm->create_domain($create_domain_param));

  $delete_domain_param = array(
  'domain' => array('edw661.co', 'edw155.co')
  );
  echo '</pre><p>delete domain: </p><pre>';
  print_r($vm->delete_domain($delete_domain_param));
  echo '</pre><p>repeat delete domain: </p><pre>';
  print_r($vm->delete_domain($delete_domain_param));

  $disable_domain_param = array(
  'domain' => 'edw4441.co',
  'why' => 'No in use'
  );
  echo '</pre><p>disable domain: </p><pre>';
  print_r($vm->disable_domain($disable_domain_param));
  $enable_domain_param = array(
  'domain' => 'edw4441.co'
  );
  echo '</pre><p>enable domain: </p><pre>';
  print_r($vm->enable_domain($enable_domain_param));
  echo '</pre><p>List domains: </p><pre>';
  print_r($vm->list_domains());
  echo '</pre>';
 *
 */
