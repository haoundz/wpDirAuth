<?php
/**
 * wpDirAuth: WordPress Directory Authentication (LDAP/LDAPS).
 * 
 * Works with most LDAP enabled directory services, such as OpenLDAP,
 * Apache Directory, Microsoft Active Directory, Novell eDirectory,
 * Sun Java System Directory Server, etc.
 * 
 * Please note that wpDirAuth will start in safe mode if it detects that
 * another plugin is in conflict, by detecting if the wp_authenticate and
 * wp_setcookie functions have already been overwritten. It cannot,
 * on the other hand, detect plugins that might want to overwrite these
 * functions after wpDirAuth has been loaded.
 * 
 * Originally forked from a patched version of wpLDAP.
 * 
 * @package wpDirAuth
 * @version 1.7.6
 * @see http://wpdirauth.gilzow.com/
 * @license GPL <http://www.gnu.org/licenses/gpl.html>
 * 
 * Copyrights are listed in chronological order, by contributions.
 * 
 * wpDirAuth: WordPress Directory Authentication, original author
 * Copyright (c) 2007 Stephane Daury - http://stephane.daury.org/
 * 
 * wpDirAuth and wpLDAP Patch Contributions
 * Copyright (c) 2007 PKR Internet, LLC - http://www.pkrinternet.com/
 *  
 * wpDirAuth Patch Contributions
 * Copyright (c) 2007 Todd Beverly
 * 
 * wpLDAP: WordPress LDAP Authentication
 * Copyright (c) 2007 Ashay Suresh Manjure - http://ashay.org/
 * 
 * wpDirAuth Patch Contribution and current maintainer
 * Copyright (c) 2010, 2011, 2012 Paul Gilzow - http://gilzow.com/
 *  
 * wpDirAuth is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation.
 * 
 * wpDirAuth is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/.
 * 
 * @todo Always stay on top of security and user input validation while
 * staying backwards compatible enough until PHP4 support is dropped in
 * WP (serious patches welcomed, please see code). Note that we do
 * heavily rely on WP's admin ACL scheme, by necessity.
 */

/*
PLUGIN META INFO FOR WORDPRESS LISTINGS
Plugin Name: wpDirAuth
Plugin URI:  http://wpdirauth.gilzow.com/
Description: WordPress Directory Authentication (LDAP/LDAPS).
             Works with most LDAP enabled directory services, such as OpenLDAP,
             Apache Directory, Microsoft Active Directory, Novell eDirectory,
             Sun Java System Directory Server, etc.
             Originally revived and upgraded from a patched version of wpLDAP.
Version: 1.7.6
Author: Paul Gilzow
Author URI: http://gilzow.com/
*/

/**
 * wpDirAuth version.
 */
define('WPDIRAUTH_VERSION', '1.7.6');

/**
 * wpDirAuth signature.
 */
define('WPDIRAUTH_SIGNATURE', '<a href="http://wordpress.org/extend/plugins/wpdirauth/">wpDirAuth</a> '.WPDIRAUTH_VERSION);

/**
 * Default LDAP field to search against when locating the user's profile.
 */
define('WPDIRAUTH_DEFAULT_FILTER', 'samAccountName');

/**
 * Default login screen message.
 */
define('WPDIRAUTH_DEFAULT_LOGINSCREENMSG', '%s members can login directly using their institutional password.');

/**
 * Default password change message.
 */
define('WPDIRAUTH_DEFAULT_CHANGEPASSMSG', 'To change a %s password, please refer to the official institutional password policy.');

/**
 * Allowed HTML (messages)
 */
define('WPDIRAUTH_ALLOWED_TAGS', '<a><strong><em><p><ul><ol><li>');

define('WPDIRAUTH_ERROR_TITLE',__('<strong>Directory Authentication Error</strong>: '));

define('WPDIRAUTH_LDAP_RETURN_KEYS',serialize(array('sn', 'givenname', 'mail')));

define('WPDIRAUTH_EMAIL_NEWUSER_NOTIFY','You have been added to the site %s as %s %s. You may login to the site using your institution\'s %s (%s) and password at the following address: %s');

/**
 *My fail-safe method for determing if we are running in multisite mode 
 */
define('WPDIRAUTH_MULTISITE',(defined('WP_ALLOW_MULTISITE') && WP_ALLOW_MULTISITE && function_exists('switch_to_blog')) ? TRUE : FALSE);

/**
 *List of option keys we store in wp_sitemeta/wp_options 
 */
define('WPDIRAUTH_OPTIONS', serialize(array(
    'dirAuthEnable',
    'dirAuthRequiresSsl',
    'dirAuthTOS',
    'dirAuthUseGroups',
    'dirAuthEnableSsl',
    'dirAuthControllers',
    'dirAuthBaseDn',
    'dirAuthPreBindUser',
    'dirAuthAccountSuffix',
    'dirAuthFilter',
    'dirAuthInstitution',
    'dirAuthGroups',
    'dirAuthMarketingSSOID',
    'dirAuthLoginScreenMsg',
    'dirAuthChangePassMsg'
)));


if (function_exists('wp_authenticate') || function_exists('wp_setcookie') || !function_exists('ldap_connect')) {
/**
 * SAFE MODE
 */
    
    /**
     * SAFE MODE: wpDirAuth plugin configuration panel.
     * Processes and outputs the wpDirAuth configuration form, with a conflict message.
     * 
     * @return void
     */
    function wpDirAuth_safeConflictMessage()
    {
        $wpDARef = WPDIRAUTH_SIGNATURE;
        
        if (!function_exists('ldap_connect')) {
            $message = <<<________EOS
            <h3>Sorry, but your PHP install does not seem to have access to the LDAP features.</h3>
            <p>
                <br />wpDirAuth is now running in safe mode.'
            </p>
            <p>
                Quote from the <a href="http://php.net/ldap#ldap.installation">PHP manual LDAP section</a>:
                <blockquote>
                     LDAP support in PHP is not enabled by default. You will need to use the
                     --with-ldap[=DIR] configuration option when compiling PHP to enable LDAP
                     support. DIR is the LDAP base install directory. To enable SASL support,
                     be sure --with-ldap-sasl[=DIR] is used, and that sasl.h exists on the system.
                </blockquote>
            </p>
________EOS;
        }
        else {
            $message = <<<________EOS
            <h3>Sorry, but another plugin seems to be conflicting with wpDirAuth.</h3>
            <p>
                <br />wpDirAuth is now running in safe mode as to not impair the other plugin's operations.'
            </p>
            <p>
                The wp_authenticate and wp_setcookie WordPress
                <a href="http://codex.wordpress.org/Pluggable_Functions">pluggable functions</a>
                have already been redefined, and wpDirAuth cannot provide directory authentication
                without having access to these functions.
            </p>
            <p>
                Please disable any WP plugins that deal with authentication in order to use wpDirAuth.
                Unfortunately, we cannot provide you with more info as to which plugin is in conflict.
            </p>
________EOS;
        }
        
        echo <<<________EOS
        <div class="wrap">
            <h2>Directory Authentication Options: Plugin Conflict</h2>
            $message
            <p>$wpDARef</p>
        </div>        
________EOS;
    }
    

    /**
     * SAFE MODE: Adds the `Directory Auth.` menu entry in the Wordpress Admin section.
     * Also activates the wpDirAuth config panel, with a conflict message, as a callback function.
     * 
     * @uses wpDirAuth_safeConflictMessage
     */
    function wpDirAuth_safeAddMenu() 
    {
        if (function_exists('add_options_page')) {
            add_options_page(
                'Directory Authentication Options: Plugin Conflict',
                '!! Directory Auth. !!',
                9,
                basename(__FILE__),
                'wpDirAuth_safeConflictMessage'
            );
        }
    }


    /**
     * SAFE MODE: Add custom WordPress actions.
     * 
     * @uses wpDirAuth_safeAddMenu
     */
    if (function_exists('add_action')) {
        add_action('admin_menu', 'wpDirAuth_safeAddMenu');
    }    
}
else {
/**
 * STANDARD MODE
 */
 
    /**
     * Cookie marker.
     * Generates a random string to be used as salt for the password
     * hash cookie checks in wp_setcookie and wp_authenticate
     *
     * @return string 55 chars-long salty goodness (md5 + uniqid)
     */
    function wpDirAuth_makeCookieMarker()
    {
        $cookieMarker = md5(
             $_SERVER['SERVER_SIGNATURE']
            .$_SERVER['HTTP_USER_AGENT']
            .$_SERVER['REMOTE_ADDR']
        ).uniqid(microtime(),true);
        update_site_option("dirAuthCookieMarker",$cookieMarker);
        return $cookieMarker;
    }
    
    
    /**
     * LDAP bind test
     * Tries two different documented method of php-based ldap binding.
     * Note: passing params by reference, no need for copies (unlike in
     * wpDirAuth_auth where it is desirable).
     * 
     * @param object &$connection LDAP connection
     * @param string &$username LDAP username
     * @param string &$password LDAP password
     * @param string $baseDn
     * @return boolean Binding status
     *      */
    function wpDirAuth_bindTest(&$connection, &$username, &$password,$baseDn)
    {
    $password = strtr($password, array("\'"=>"'"));
        if ( ($isBound = @ldap_bind($connection, $username, $password)) === false ) {
            // @see http://weblogs.valsania.it/andreav/2008/07/24/wpdirauth-14-patch/
            $isBound = @ldap_bind($connection,"uid=$username,$baseDn", $password);
        }
        return $isBound;
    }
    
    /**
    * put your comment there...
    * 
    * @param string $dc name of domain controller to connect to
    * @param integer $enableSsl ssl config option
    * @return resource
    */
    function wpDirAuth_establishConnection($dc,$enableSsl){
        /**
         * Only setup protocol value if ldaps is required to help with older AD
         * @see http://groups.google.com/group/wpdirauth-support/browse_thread/thread/7b744c7ad66a4829
         */
        $protocol = ($enableSsl) ? 'ldaps://' : '';
        
        /**
         * Scan for and use alternate server port, but only if ssl is disabled.
         * @see Parameters constraint at http://ca.php.net/ldap_connect
         */
        
        if (strstr($dc, ':')) list($dc, $port) = explode(':', $dc);

        switch($enableSsl){
            case 1:
                $connection = ldap_connect($protocol.$dc);
                break;
            case 2:
            case 0:
            default:
                if(isset($port)){
                    $connection = ldap_connect($dc,$port);
                } else {
                    $connection = ldap_connect($dc);
                }                
                break;                

        }
        
        /**
         * Copes with W2K3/AD issue.
         * @see http://bugs.php.net/bug.php?id=30670
         */
        if (@ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            @ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        }
        
        //they want to start TLS
        if($enableSsl == 2){
            if(!ldap_start_tls($connection)){
                return new WP_Error('tls_failed_to_start',__('wpDirAuth error: tls failed to start'));
            }
        } 
        
        return $connection;       
    }

    /**
    * put your comment there...
    * 
    * @param array $controllers list of domain controllers to connect to
    * @return mixed array of shuffled controllers or WP_Error
    */
    function wpDirAuth_shuffleControllers($controllers){
        if (count($controllers) > 1) {
            // shuffle the domain controllers for pseudo load balancing and fault tolerance.
            shuffle($controllers);
        } elseif (count($controllers) == 0) {
            return new WP_Error('no_controllers',__(' wpDirAuth config error: no domain controllers specified.'));
        }  
        
        return $controllers;      
    }
    
    /**
     * Custom LDAP authentication module.
     * The returned keys are in the same format used by WP for
     * the wp_insert_user and wp_update_user functions.
     *
     * @param string $username LDAP username
     * @param string $password LDAP password
     * @return WP_Error object OR array Directory email, last_name and first_name
     * 
     * @uses WPDIRAUTH_DEFAULT_FILTER
     * @uses WPDIRAUTH_ERROR_TITLE
     * @uses wpDirAuth_bindTest
     * @uses wpDirAuth_retrieveUserDetails
     * @uses wpDirAuth_shuffleControllers
     * @uses wpDirAuth_establishConnection
     */
    function wpDirAuth_auth($username, $password)
    {
        global $error, $pwd;  
        
        $errorTitle = WPDIRAUTH_ERROR_TITLE;
        
        $controllers      = explode(',', get_site_option('dirAuthControllers'));
		//base dn array 
        $baseDn           =  explode(';', get_site_option('dirAuthBaseDn'));// $baseDn = get_site_option('dirAuthBaseDn');
        $preBindUser      = get_site_option('dirAuthPreBindUser');
        $preBindPassword  = get_site_option('dirAuthPreBindPassword');
        $accountSuffix    = get_site_option('dirAuthAccountSuffix');
        $filter           = get_site_option('dirAuthFilter');
        $enableSsl        = get_site_option('dirAuthEnableSsl');
        $boolUseGroups    = get_site_option('dirAuthUseGroups');
        
        if($boolUseGroups == 1){
            $strAuthGroups = get_site_option('dirAuthGroups');
        }
        
        $returnKeys = unserialize(WPDIRAUTH_LDAP_RETURN_KEYS);
    
        $isBound = $isPreBound = $isLoggedIn = false;
        
        if ($accountSuffix) $username .= $accountSuffix;
    
        if (!$filter) $filter = WPDIRAUTH_DEFAULT_FILTER;
        
        $filterQuery = "($filter=$username)";
        
        $controllers = wpDirAuth_shuffleControllers($controllers);
        
        if(is_wp_error($controllers)){
            return $controllers;
        }
        
        // Connection pool loop - Haha, PooL LooP 
        foreach ($controllers as $dc) {
            
            $connection = wpDirAuth_establishConnection($dc,$enableSsl);
            
            if(is_wp_error($connection)){
                return $connection;
            }
            
            if ($preBindUser && $preBindPassword) {
                /**
                 * Use case 1: Servers requiring pre-binding with admin defined
                 * credentials to search for the user's full DN before attempting
                 * to login.
                 * @see http://dev.wp-plugins.org/ticket/681
                 */
                if ( $isPreBound = wpDirAuth_bindTest($connection, $preBindUser, $preBindPassword,$baseDn) === true ) {
                    if ( ($results = @ldap_search($connection, $baseDn, $filterQuery, $returnKeys)) !== false ) {
                        if ( ($userDn = @ldap_get_dn($connection, ldap_first_entry($connection, $results))) !== false ) {
                            if ( ($isBound = wpDirAuth_bindTest($connection, $userDn, $password,$baseDn)) === true ) {
                                $isLoggedIn = true; // valid server, valid login, move on
                                break; // valid server, valid login, move on
                            }
                        }
                    }
                }
            }
            elseif ( ($isBound = wpDirAuth_bindTest($connection, $username, $password,$baseDn)) === true ) {
                /**
                 * Use case 2: Servers that will not let you bind anonymously
                 * but will let the end user bind directly.
                 * @see http://groups.google.com/group/wpdirauth-support/browse_thread/thread/8fd16c05266fc832
                 */
                $isLoggedIn = true;
                break;  // valid server, valid login, move on
            }
            elseif ( ($isBound = @ldap_bind($connection)) === true ) {
                /**
                 * Use case 3: Servers that might require a full user DN to
                 * actually login and therefore let you bind anonymously first .
                 * Try ldap_search + ldap_get_dn before attempting a login.
                 * @see http://wordpress.org/support/topic/129814?replies=34#post-603644
                 */
                if ( ($results = @ldap_search($connection, $baseDn, $filterQuery, $returnKeys)) !== false ) {
                    if ( ($userDn = @ldap_get_dn($connection, ldap_first_entry($connection, $results))) !== false ) {
                        $isInDirectory = true; // account exists in directory
                        if ( ($isBound = wpDirAuth_bindTest($connection, $userDn, $password,$baseDn)) === true ) {
                            $isLoggedIn = true; // valid server, valid login, move on
                            break; // valid server, valid login, move on
                        }
                    }
                }
            }
        }
        
        if ( ($preBindUser && $preBindPassword) && ( ! $isPreBound ) ) {
            return new WP_Error ('no_directory_or_prebinding', $errorTitle
                   . __(' wpDirAuth config error: No directory server available for authentication, OR pre-binding credentials denied.'));
        }
    elseif ( ( $isInDirectory ) && ( ! $isBound ) ) {
            return new WP_Error ('could_not_bind_as_user', $errorTitle
                   . __(' Incorrect password.'));
    }
        elseif ( ! $isBound && ! $isPreBound ) {
            return new WP_Error ('no_directory_available', $errorTitle
                   . __(' wpDirAuth config error: No directory server available for authentication.'));
        }
        elseif ( ! $isLoggedIn) {
            /**
            * @desc wp-hack was echo'ing out $username verbatim which allowed a XSS vulnerability. Encoded $username before echoing'
            */
            return new WP_Error ('could_not_authenticate', $errorTitle
                   . __(' Could not authenticate user. Please check your credentials.')
                   . " [" . htmlentities($username,ENT_QUOTES,'UTF-8') . "]");

            
        }
        else {
            if($boolUseGroups == 1){
                //the user is authenticated, but we want to make sure they are a member of the groups given
                /**
                * We need to get the DN's for each Authentication Group CN that was given to us. 
                */
                $aryAuthGroupsDN = array();
                $aryAuthGroups = explode(',',$strAuthGroups);
                $aryAttribs = array('distinguishedname');
                foreach($aryAuthGroups as $strAuthGroup){
                    $strAuthGroup = 'cn='.$strAuthGroup;
                    $rscLDAPSearch = ldap_search($connection,$baseDn,$strAuthGroup,$aryAttribs);
                    $arySearchResults = ldap_get_entries($connection,$rscLDAPSearch);
                    if(isset($arySearchResults[0]['dn'])){
                        $aryAuthGroupsDN[] = $arySearchResults[0]['dn'];    
                    }    
                }
                
                if(count($aryAuthGroupsDN) == 0){
                    return new WP_Error('no_auth_groups_found',$errorTitle.__('No Authentication Groups found based on given group CN'));
                }                
                               
                
                $strFilterQuery = '(&'.$filterQuery.'(|';
                foreach($aryAuthGroupsDN as $strAuthGroupDN){
                    $strFilterQuery .= '(memberOf='.$strAuthGroupDN.')';
                }
                $strFilterQuery .= '))';
                if(($rscLDAPSearchGroupMember = ldap_search($connection,$baseDn,$strFilterQuery)) !== false){
                    $arySearchResultsMember = ldap_get_entries($connection,$rscLDAPSearchGroupMember);
                    if($arySearchResultsMember['count'] !== 1){
                        return new WP_Error('not_member_of_auth_group',$errorTitle
                            . __('User authenticated but is not a member of an Authentication Group(s)'));
                    }
                }
                
            }            
            
            
            /**
             * Search for profile, if still needed.
             * @see $results in preceding loop: Use case 3
             */
            if (!$results){
                return wpDirAuth_retrieveUserDetails($connection,$baseDn,$filterQuery);        
            } else {
                return wpDirAuth_retrieveUserDetails($connection,$baseDn,$filterQuery,$results);   
            }
        }
    }
    
    
    /**
     * Runs stripslashes, html_entity_decode, then strip_tags with
     * allowed html if requested.
     *  
     * No input sashimi for us (hopefully).
     *
     * @param string $value Value to `sanitize`
     * @param boolean $allowed Set to true for WPDIRAUTH_ALLOWED_TAGS
     * @return string Cleaner value.
     * 
     * @uses WPDIRAUTH_ALLOWED_TAGS
     */
    function wpDirAuth_sanitize($value, $allowed = false)
    {
        $allowed = ($allowed) ? WPDIRAUTH_ALLOWED_TAGS : '';
        return strip_tags(html_entity_decode(stripslashes($value)), $allowed);
    }
    
    
    /**
     * wpDirAuth plugin configuration panel.
     * Processes and outputs the wpDirAuth configuration form.
     * 
     * @return void
     * 
     * @uses WPDIRAUTH_DEFAULT_FILTER
     * @uses WPDIRAUTH_DEFAULT_LOGINSCREENMSG
     * @uses WPDIRAUTH_DEFAULT_CHANGEPASSMSG
     * @uses WPDIRAUTH_ALLOWED_TAGS
     * @uses wpDirAuth_makeCookieMarker
     * @uses wpDirAuth_sanitize
     */
    function wpDirAuth_optionsPanel()
    {
        global $userdata;
        
        $wpDARef     = WPDIRAUTH_SIGNATURE;
        $allowedHTML = htmlentities(WPDIRAUTH_ALLOWED_TAGS);
        
        $curUserIsDirUser = get_usermeta($userdata->ID, 'wpDirAuthFlag');
        
        if ($curUserIsDirUser) {
            echo <<<____________EOS
            <div class="wrap">
                <h2>Directory Authentication Options</h2>
                <p>
                    Because any changes made to directory authentication
                    options can adversly affect your session when logged in
                    as a directory user, you must be logged in as a
                    WordPress-only administrator user to update these settings.
                </p>
                <p>
                    If such a user no longer exists in the database, please
                    <a href="./users.php#add-new-user">create a new one</a>
                    using the appropriate WordPress admin tool.
                </p>
                <p>$wpDARef</p>
            </div>        
____________EOS;
            return;
        }
        
        if ($_POST) {
            $enableSsl        = 0; //default
            $boolUseGroups    = 0; //default
            // Booleans
            $enable           = intval($_POST['dirAuthEnable'])      == 1 ? 1 : 0;
            $requireSsl       = intval($_POST['dirAuthRequireSsl'])  == 1 ? 1 : 0;
            $TOS              = intval($_POST['dirAuthTOS'])         == 1 ? 1 : 0;

            //integers
            if(intval($_POST['dirAuthEnableSsl']) == 1 || intval($_POST['dirAuthEnableSsl']) == 2){
                 $enableSsl        = intval($_POST['dirAuthEnableSsl']);
            }
          
            
            // Strings, no HTML
            $controllers      = wpDirAuth_sanitize($_POST['dirAuthControllers']);
            $baseDn           = wpDirAuth_sanitize($_POST['dirAuthBaseDn']);
            $preBindUser      = wpDirAuth_sanitize($_POST['dirAuthPreBindUser']);
            $preBindPassword  = wpDirAuth_sanitize($_POST['dirAuthPreBindPassword']);
            $preBindPassCheck = wpDirAuth_sanitize($_POST['dirAuthPreBindPassCheck']);
            $accountSuffix    = wpDirAuth_sanitize($_POST['dirAuthAccountSuffix']);
            $filter           = wpDirAuth_sanitize($_POST['dirAuthFilter']);
            $institution      = wpDirAuth_sanitize($_POST['dirAuthInstitution']);
            $strAuthGroups    = wpDirAuth_sanitize($_POST['dirAuthGroups']);
            $strMarketingSSOID= wpDirAuth_sanitize(($_POST['dirAuthMarketingSSOID']));

            if($strAuthGroups != ''){
                $boolUseGroups = 1;    
            }            
            
            // Have to be allowed to contain some HTML
            $loginScreenMsg   = wpDirAuth_sanitize($_POST['dirAuthLoginScreenMsg'], true);
            $changePassMsg    = wpDirAuth_sanitize($_POST['dirAuthChangePassMsg'], true);
            
            update_site_option('dirAuthEnable',          $enable);
            update_site_option('dirAuthEnableSsl',       $enableSsl);
            update_site_option('dirAuthRequireSsl',      $requireSsl);
            update_site_option('dirAuthControllers',     $controllers);
            update_site_option('dirAuthBaseDn',          $baseDn);
            update_site_option('dirAuthPreBindUser',     $preBindUser);
            update_site_option('dirAuthAccountSuffix',   $accountSuffix);
            update_site_option('dirAuthFilter',          $filter);
            update_site_option('dirAuthInstitution',     $institution);
            update_site_option('dirAuthLoginScreenMsg',  $loginScreenMsg);
            update_site_option('dirAuthChangePassMsg',   $changePassMsg);
            update_site_option('dirAuthTOS',             $TOS);
            update_site_option('dirAuthUseGroups',       $boolUseGroups);
            update_site_option('dirAuthGroups',          $strAuthGroups);
            update_site_option('dirAuthMarketingSSOID',  $strMarketingSSOID);

            
            // Only store/override the value if a new one is being sent a bind user is set.
            if ( $preBindUser && $preBindPassword && ($preBindPassCheck == $preBindPassword) )
                update_site_option('dirAuthPreBindPassword', $preBindPassword);
            
            // Clear the stored password if the Bind DN is null
            elseif ( ! $preBindUser)
                update_site_option('dirAuthPreBindPassword', '');
    
            if (get_site_option('dirAuthEnable') && !get_site_option('dirAuthCookieMarker'))
                wpDirAuth_makeCookieMarker();
            
            echo '<div id="message" class="updated fade"><p>Your new settings were saved successfully.</p></div>';
            
            // Be sure to clear $preBindPassword, not to be displayed onscreen or in source
            unset($preBindPassword);
        }
        else {        
            // Booleans
            $enable          = intval(get_site_option('dirAuthEnable'))     == 1 ? 1 : 0;
            $requireSsl      = intval(get_site_option('dirAuthRequireSsl')) == 1 ? 1 : 0;
            $TOS             = intval(get_site_option('dirAuthTOS'))        == 1 ? 1 : 0;
            $boolUseGroups   = intval(get_site_option('dirAuthUseGroups'))  == 1 ? 1 : 0;
            
            //integers
            $enableSsl       = intval(get_site_option('dirAuthEnableSsl',0));
            
            // Strings, no HTML
            $controllers        = wpDirAuth_sanitize(get_site_option('dirAuthControllers'));
            $baseDn             = wpDirAuth_sanitize(get_site_option('dirAuthBaseDn'));
            $preBindUser        = wpDirAuth_sanitize(get_site_option('dirAuthPreBindUser'));
            $accountSuffix      = wpDirAuth_sanitize(get_site_option('dirAuthAccountSuffix'));
            $filter             = wpDirAuth_sanitize(get_site_option('dirAuthFilter'));
            $institution        = wpDirAuth_sanitize(get_site_option('dirAuthInstitution'));
            $strAuthGroups      = wpDirAuth_sanitize((get_site_option('dirAuthGroups')));
            $strMarketingSSOID  = wpDirAuth_sanitize((get_site_option('dirAuthMarketingSSOID'))); 
            
            // Have to be allowed to contain some HTML
            $loginScreenMsg  = wpDirAuth_sanitize(get_site_option('dirAuthLoginScreenMsg'), true);
            $changePassMsg   = wpDirAuth_sanitize(get_site_option('dirAuthChangePassMsg'), true);
        }

        $controllers    = htmlspecialchars($controllers);
        $baseDn         = htmlspecialchars($baseDn);
        $preBindUser    = htmlspecialchars($preBindUser);
        $accountSuffix  = htmlspecialchars($accountSuffix);
        $filter         = htmlspecialchars($filter);
        $institution    = htmlspecialchars($institution);
        $loginScreenMsg = htmlspecialchars($loginScreenMsg);
        $changePassMsg  = htmlspecialchars($changePassMsg);
        $strAuthGroups  = htmlspecialchars($strAuthGroups);
        
        if ($enable) {
            $tEnable = "checked";
        }
        else {
            $fEnable = "checked";
        }
        
        $defaultFilter = WPDIRAUTH_DEFAULT_FILTER;
        if (!$filter) {
            $filter = $defaultFilter;
        }
        
        if (!$institution) {
            $institution = '[YOUR INSTITUTION]';
        }
        
        if (!$loginScreenMsg) {
            $loginScreenMsg = sprintf(WPDIRAUTH_DEFAULT_LOGINSCREENMSG, $institution);
        }
        
        if (!$changePassMsg) {
            $changePassMsg = sprintf(WPDIRAUTH_DEFAULT_CHANGEPASSMSG, $institution);
        }
        
        /*
        if ($enableSsl) {
            $tSsl = "checked";
        }
        else {
            $fSsl = "checked";
        }
        */
        $strNoSSL ='';
        $strSSL = '';
        $strTLS = '';
        $strOptionSelected = 'selected="selected"';
        switch($enableSsl){
            case 1:
                $strSSL = $strOptionSelected;
                break;
            case 2:
                $strTLS = $strOptionSelected;
                break;
            case 0:
            default:
                $strNoSSL = $strOptionSelected; 
                break;

        }
        
        if ($requireSsl) {
            $tWpSsl = "checked";
        }
        else {
            $fWpSsl = "checked";
        }
        
        if ($TOS) {
            $tTOS = "checked";
        }
        else {
            $fTOS = "checked";
        }
        
        $wpDAV = WPDIRAUTH_VERSION;
        
        echo <<<________EOS
        <div class="wrap">
            <h2>Directory Authentication Options</h2>
            <form method="post" id="dir_auth_options">
                <p class="submit"><input type="submit" name="dirAuthOptionsSave" value="Update Options &raquo;" /></p>
                <fieldset class="options">
                    <legend>WordPress Settings</legend>  
                    <ul>
                        <li>
                            <label for="dirAuthEnable"><strong>Enable Directory Authentication?</strong></label>
                            <br />
                            <input type="radio" name="dirAuthEnable" value="1" $tEnable /> Yes &nbsp;
                            <input type="radio" name="dirAuthEnable" value="0" $fEnable /> No
                            <br />
                            <strong>Note 1</strong>: Users created in WordPress are not affected by your directory authentication settings.
                            <br />
                            <strong>Note 2</strong>: You will still be able to login with standard WP users if the LDAP server(s) go offline.
                            </li>
                        <li>
                            <label for="dirAuthRequireSsl"><strong>Require SSL Login?</strong></label>
                            <br />
                            <input type="radio" name="dirAuthRequireSsl" value="1" $tWpSsl/> Yes &nbsp;
                            <input type="radio" name="dirAuthRequireSsl" value="0" $fWpSsl/> No
                            <br />
                            <em>Force the WordPress login screen to require encryption (SSL, https:// URL)?</em>
                        </li>
                    </ul>
                </fieldset>
                <fieldset class="options">
                    <legend>Directory Settings</legend>
                    <ul>
                        <li>
                            <label for="dirAuthEnableSsl"><strong>Enable SSL Connectivity?</strong></label>
                            <br />
                            <!--
                            <input type="radio" name="dirAuthEnableSsl" value="1" $tSsl/> Yes &nbsp;
                            <input type="radio" name="dirAuthEnableSsl" value="0" $fSsl/> No
                            -->
                            <select id="dirAuthEnableSsl" name="dirAuthEnableSsl">
                                <option value="0" $strNoSSL>No SSL Connectivity</option>
                                <option value="1" $strSSL>Use SSL (ldaps)</option>
                                <option value="2" $strTLS>Use TLS</option>
                            </select>
                            <br />
                            <em>Use encryption (TLS, SSL, ldaps:// URL) when WordPress connects to the directory server(s)?</em>
                        </li>
                        <li>
                            <label for="dirAuthControllers"><strong>Directory Servers (Domain Controllers)</strong></label>
                            <br />
                            <input type="text" name="dirAuthControllers" value="$controllers" size="40"/><br />
                            <em>The DNS name or IP address of the directory server(s).</em><br />
                            <strong>NOTE:</strong> Separate multiple entries by a comma and/or alternate ports with a colon (eg: my.server1.org, my.server2.edu:387).
                            Unfortunately, alternate ports will be ignored when using LDAP/SSL, because of <a href="http://ca3.php.net/ldap_connect">the way</a> PHP handles the protocol.
                            
                        </li>
                        <li>
                            <label for="dirAuthFilter"><strong>Account Filter</strong></label>
                            <br />
                            <input type="text" name="dirAuthFilter" value="$filter" size="40"/>
                            (Defaults to <em>$defaultFilter</em>) 
                            <br />
                            <em>What LDAP field should we search the username against to locate the user's profile after successful login?</em>
                        </li>
                        <li>
                            <label for="dirAuthAccountSuffix"><strong>Account Suffix</strong></label>
                            <br />
                            <input type="text" name="dirAuthAccountSuffix" value="$accountSuffix" size="40" /><br />
                            <em>Suffix to be automatically appended to the username if desired. e.g. @domain.com</em><br />
                            <strong>NOTE:</strong> Changing this value will cause your existing directory users to have new accounts created the next time they login.
                        </li>
                        <li>
                            <label for="dirAuthBaseDn"><strong>Base DN</strong></label>
                            <br />
                            <input type="text" name="dirAuthBaseDn" value="$baseDn" size="40"/><br />
                            <em>The base DN for carrying out LDAP searches.</em><br />
							<strong>NOTE:</strong> Separate multiple entries by a semicolon (eg: o=basedn1;o=basedn2).
                        </li>
                        <li>
                            <label for="dirAuthPreBindUser"><strong>Bind DN</strong></label>
                            <br />
                            <input type="text" name="dirAuthPreBindUser" value="$preBindUser" size="40"/><br />
                            <em>Enter a valid user account/DN to pre-bind with if your LDAP server does not allow anonymous profile searches, or requires a user with specific privileges to search.</em>
                        </li>
                        <li>
                            <label for="dirAuthPreBindPassword"><strong>Bind Password</strong></label>
                            <br />
                            <input type="password" name="dirAuthPreBindPassword" value="" size="40"/><br />
                            <em>Enter a password for the above Bind DN if a value is needed.</em><br />
                            <strong>Note 1</strong>: this value will be stored in clear text in your WordPress database.<br />
                            <strong>Note 2</strong>: Simply clear the Bind DN value if you wish to delete the stored password altogether.
                        </li>
                        <li>
                            <label for="dirAuthPreBindPassCheck"><strong>Confirm Password</strong></label>
                            <br />
                            <input type="password" name="dirAuthPreBindPassCheck" value="" size="40"/><br />
                            <em>Confirm the above Bind Password if you are setting a new value.</em>
                        </li>
                        <li>
                            <label for="dirAuthGroups"><strong>Authentication Groups</strong></label><br />
                            <input type="text" name="dirAuthGroups" id="dirAuthGroups" size="40" value="$strAuthGroups" /><br />
                            <em>Enter each group CN that the user must be a member of in order to authenticate.</em> <br />
                            <strong>NOTE:</strong> Separate multiple CNs by a comma.
                        </li>
                    </ul>
                </fieldset>
                <fieldset class="options">
                    <legend>Branding Settings</legend>
                    <ul>
                        <li>
                            <label for="dirAuthInstitution"><strong>Institution Name</strong></label>
                            <br />
                            <input type="text" name="dirAuthInstitution" value="$institution" size="40" />
                            <br />
                            <em>Name of your institution/company. Displayed on the login screen.</em>
                        </li>
                        
                        <li>
                            <label for=""><strong>Marketing name for Institutional Single-Sign-On ID</strong></label>
                            <br />
                            <input type="text" name="dirAuthMarketingSSOID" value="$strMarketingSSOID" id="dirAuthMarketingSSOID" size="40" />
                            <br />
                            <em>How your institution/company refers to the single-sign-on ID you use.</em>
                        </li>
                        <li>
                            <label for="dirAuthLoginScreenMsg"><strong>Login Screen Message</strong></label>
                            <br />
                            <textarea name="dirAuthLoginScreenMsg" cols="40" rows="3">$loginScreenMsg</textarea>
                            <br />
                            <em>Displayed on the login screen, underneath the username/password fields.</em><br />
                            <strong>Note</strong>: Some HTML allowed: $allowedHTML
                        </li>
                        <li>
                            <label for="dirAuthChangePassMsg"><strong>Password Change Message</strong></label>
                            <br />
                            <textarea name="dirAuthChangePassMsg" cols="40" rows="3">$changePassMsg</textarea>
                            <br />
                            <em>Displayed wherever user passwords can be changed, for directory users only.</em><br />
                            <strong>Note</strong>: Some HTML allowed: $allowedHTML
                            
                        </li>
                        <li>
                            <label for="dirAuthTOS"><strong>Terms of Services Agreement</strong></label>
                            <br />
                            <input type="radio" name="dirAuthTOS" value="1" $tTOS/> Yes &nbsp;
                            <input type="radio" name="dirAuthTOS" value="0" $fTOS/> No
                            <br />
                            <em>Ask directory users to agree to terms of services that you link to in the message above?</em><br />
                            <strong>Note</strong>: Checkbox disappears once checked, date of agreement is stored and users are no longer prompted.
                        </li>
                        </ul>
                </fieldset>
                <p class="submit"><input type="submit" name="dirAuthOptionsSave" value="Update Options &raquo;" /></p>
            </form>
            <p>Powered by $wpDARef.</p>
        </div>
________EOS;
    }
    

    /**
     * Adds the `Directory Auth.` menu entry in the Wordpress Admin section, and the `Add Directory Authenticated User` to the Users menu
     * Also activates the wpDirAuth config panel as a callback function.
     * 
     * @uses wpDirAuth_optionsPanel
     * @uses wpDirAuth_add_user_panel
     */
    function wpDirAuth_addMenu() 
    {
        if (function_exists('add_options_page')) {
            add_options_page(
                'Directory Authentication Options',
                'Directory Auth.',
                9,
                basename(__FILE__),
                'wpDirAuth_optionsPanel'
            );
        }
    }
    
    
    /**
     * Extending WP's login_form.
     * Enforces the admin defined SSL login preferences and adds a directory
     * login related message to the standard WP login screen.
     * 
     * @uses WPDIRAUTH_DEFAULT_LOGINSCREENMSG
     */
    function wpDirAuth_loginFormExtra()
    {
        if (get_site_option('dirAuthEnable')) {
            
            if (isset($_SERVER['SCRIPT_URI']) && preg_match('|^http|',$_SERVER['SCRIPT_URI'])) {
                $selfURL = $_SERVER['SCRIPT_URI'];
            }
            else {
                /**
                 * $_SERVER['SCRIPT_URI'] seems to be unavilable in some PHP
                 * installs, and $_SERVER['REQUEST_URI'] and $_SERVER['PHP_SELF']
                 * have been known to sometimes have the same issue.
                 * Thanks to Todd Beverly for helping out with this one. :)
                 * @see http://wordpress.org/support/topic/129814?replies=27#post-605423
                 */
                $selfURL = sprintf(
                    'http%s://%s%s',
                    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 's' : ''),
                    $_SERVER['HTTP_HOST'],
                    (isset($_SERVER['REQUEST_URI'])
                        ? $_SERVER['REQUEST_URI']
                        : $_SERVER["SCRIPT_NAME"].'?'.$_SERVER['QUERY_STRING'])
                );
            }
            
            //_log('contents of selfurl: '.$selfURL);
            
            
            if (get_site_option('dirAuthRequireSsl') && (!preg_match('|^https|',$selfURL))) {
                $sslURL = str_replace('http://','https://',$selfURL);
                
                $refreshJS   = '<script type="text/javascript">'."\n".'top.location.href=\''.$sslURL.'\';'."\n".'</script>" />';
                $refreshMeta = '<meta http-equiv="refresh" content="0;url='.$sslURL.'" />';
                $refreshMsg  = 'Please access the <a href="'.$sslURL.'">encrypted version</a> of this page.';
                
                if (headers_sent()) {
                        echo $refreshJS.$refreshMeta.'<p>'.$refreshMsg.'</p></form></div></html>';
                }
                else {
                    @ob_end_clean();
                    if (!@header('Location:'.$sslURL)) {
                        echo '<html><head>'.$refreshJS.$refreshMeta.'</head>'
                           . '<body>'.$refreshMsg.'</body></html>';
                    }
                }
                
                exit;
            }
    
            $dirAuthInstitution = stripslashes(get_site_option('dirAuthInstitution'));
            if (!$dirAuthInstitution) $dirAuthInstitution = __('Directory');
            
            $loginScreenMsg = stripslashes(get_site_option('dirAuthLoginScreenMsg'));
            if (!$loginScreenMsg) $loginScreenMsg = __(sprintf(
                WPDIRAUTH_DEFAULT_LOGINSCREENMSG,
                get_site_option('dirAuthInstitution')
            ));
            
            echo '
                <style>.wpDirAuthMsg a, .wpDirAuthMsg a:visited {color: #ebcd4e;}</style>
                <p class="wpDirAuthMsg">'.$loginScreenMsg.'</p>
            ';
        }
    }
    
    
    /**
     * Extending WP's show_password_fields.
     * Displays the directory password change message in profile.php and user.php.
     *
     * @return boolean Return format as expected by WP's show_password_fields()
     * 
     * @uses WPDIRAUTH_DEFAULT_CHANGEPASSMSG
     */
    function wpDirAuth_hidePassFields()
    {
        global $profileuser, $userdata;
        
        $editUserIsDirUser = get_usermeta($profileuser->ID, 'wpDirAuthFlag');
        
        if (!$editUserIsDirUser) {
            return true;
        }
        else {
            // Editing directory user profile, show password msg
            $message = stripslashes(get_site_option('dirAuthChangePassMsg'));
            if (!$message) {
                $message = __(sprintf(
                    WPDIRAUTH_DEFAULT_CHANGEPASSMSG,
                    stripslashes(get_site_option('dirAuthInstitution'))
                ));
            }
            
            if (get_site_option('dirAuthTOS')) {
                // TOS option is ON
                if (($TOSDate = get_usermeta($profileuser->ID, 'wpDirAuthTOS')) === '') {
                    if ($userdata->ID == $profileuser->ID) {
                        // Only show TOS acceptance checkbox to the owner of the profile.
                        $message .= '</p><p class="desc">'
                                 .  '<input type="checkbox" name="wpDirAuthTOS" value="1" style="width:15px; height:15px;" /> '
                                 .  __('Accept terms of services.')
                                 .  '</p><p class="desc">';
                    }
                    else {
                        // Show generic message to other admins.
                        $message .= '</p><p class="desc">'
                                 .  __('User has not yet agreed to the terms of services.')
                                 .  '</p><p class="desc">';
                    }
                }
                else {
                    // Show TOS acceptance date
                    $message .= '</p><p class="desc">'
                             .  __('Terms of services accepted on')
                             .  ' '.$TOSDate
                             .  '</p><p class="desc">';
                }
            }
            
            echo '<fieldset><legend>'
               . __('Directory Password Update')
               . '</legend><p class="desc">'
               . $message
               . '</p></fieldset>';
        
            return false;
        }
    }
    
    
    /**
     * Extending WP's profile_update.
     * Saves the TOS acceptance if sent.
     *
     * @param integer $userID Sent by WP profile_update action
     * @return boolean Return format as expected by WP's profile_update()
     */
    function wpDirAuth_profileUpdate($userID){
        if (intval($_POST['wpDirAuthTOS']) === 1) {
            update_usermeta($userID, 'wpDirAuthTOS', date('Y-m-d H:i:s'));
        }
        return true;
    }


    /**
     * WP's wp_authenticate overwrite.
     * Processes the directory login and creates a new user on first access.
     *
     * @param string $username Login form username.
     * @param string $password Login form password
     * @return WP_Error|WP_User WP_User object if login successful, otherwise WP_Error object.
     * 
     * @uses wpDirAuth_makeCookieMarker
     * @uses wpDirAuth_auth
     * 
     * @see http://codex.wordpress.org/Pluggable_Functions
     */
    function wp_authenticate($username, $password)
    {
        //echo 'authenticating';exit;
        $boolRestoreBlog = false;
        if(defined('WPDIRAUTH_MULTISITE') && WPDIRAUTH_MULTISITE){
            //echo 'I should switch blogs!';exit;
            global $blog_id;
            $intOriginalBlog = $blog_id;
            switch_to_blog(1); //switch to the parent blog
            $boolRestoreBlog = true;    
        }
        /**
        * @desc wp-hack for some reason, this function is being called even when a user just goes to the login page. added the next 3 lines so that
        * if the user arrives via $_GET, then we simply tell them to login
        */
        if($_SERVER['REQUEST_METHOD'] != 'POST'){
            if($boolRestoreBlog) restore_current_blog();
            return new WP_Error('incorrect_method',__('<strong>Please Login</strong>'));    
        }
        
        if (!$username) {
            if($boolRestoreBlog) restore_current_blog();
            return new WP_Error('empty_username', __('<strong>Login Error</strong>:
                        The username field is empty.'));
        }
    
        if (!$password) {
            if($boolRestoreBlog) restore_current_blog();
            return new WP_Error('empty_password', __('<strong>Login Error</strong>:
                        The password field is empty.'));
        }
        
        $enable       = get_site_option('dirAuthEnable');
        $cookieMarker = get_site_option('dirAuthCookieMarker');
        
        if (!$cookieMarker) {
            $cookieMarker = wpDirAuth_makeCookieMarker();
        }
        
        /**
         * Get the login object. We will use it for first user insertion or when the 
         * directory auth option is not activated.
         */
        $login = get_userdatabylogin($username);
        $loginUserIsDirUser = ($login) ? get_usermeta($login->ID, 'wpDirAuthFlag') : 0;
        
        if (!$enable && $loginUserIsDirUser) {
            /*
             * Existing directory user, but directory access has now been disabled.
             */
            if($boolRestoreBlog) restore_current_blog();
            do_action( 'wp_login_failed', $username );
            return new WP_Error('login_disabled',__('<strong>Directory Login Error</strong>:
                        Sorry, but the site administrators have disabled
                        directory access in this WordPress install.'));
        }
        elseif ($enable) {
            /**
             * Directory auth == true
             */
    
            if (!$login) {
                /**
                 * No existing account record found, try dir auth
                 */
                $userData = wpDirAuth_auth($username,$password);
                
                if ( !is_wp_error($userData) ) {
                    /**
                     * Passed directory signin, so create a new WP user
                     */
                    require_once(ABSPATH . WPINC . '/registration.php');
                    
                    $userLogin = sanitize_user($username);
                    $userEmail = apply_filters('user_registration_email', $userData['email']); 
                    
                    if (username_exists($userLogin)) {
                        /*
                         * Username exists.
                         */
                        if($boolRestoreBlog) restore_current_blog();
                        do_action( 'wp_login_failed', $username );
                        return new WP_Error('username_exists',__('<strong>Directory Login Error</strong>:
                                    Could not create a new WP user account
                                    because the directory username <strong>' . htmlentities($userLogin,ENT_QUOTES,'UTF-8') . '</strong> is already
                                    registered on this site.'));
                    }
                    elseif (email_exists($userEmail)) {
                        /*
                         * Email exists.
                         */
                        if($boolRestoreBlog) restore_current_blog();
                        do_action( 'wp_login_failed', $username );
                        return new WP_Error('email_exists',__('<strong>Directory Login Error</strong>:
                                    Could not create a new WP account because
                                    the email <strong>' . htmlentities($userEmail,ENT_QUOTES,'UTF-8') . '</strong> is
                                    already registered with this site.'));
                    }
                    else {
                        if(defined('WPDIRAUTH_MULTISITE') && WPDIRAUTH_MULTISITE && isset($boolRestoreBlog) && $boolRestoreBlog){
                            restore_current_blog();
                        }    
                        
                        if ($userID = wp_create_user($userLogin, $password, $userEmail)) {
                                $userData['ID'] = $userID;
                                $tmpAr = split('@',$userData['email']);
                                $userData['nickname'] =  str_replace('.','_',$tmpAr[0]);
                                $userData['display_name'] = $userData['first_name'].' '.$userData['last_name'];
                                unset($userData['email']);

                                wp_update_user($userData);
                                update_usermeta($userID, 'wpDirAuthFlag', 1);
                                wpDirAuth_remove_password_nag($userID);  
                                //if($boolRestoreBlog) restore_current_blog();
                                return new WP_User($userID);
                            }
                            else {
                                /*
                                * Unknown error.
                                */
                                //if($boolRestoreBlog) restore_current_blog();
                                do_action( 'wp_login_failed', $username );
                                return new WP_Error('creation_unknown_error',__('<strong>Directory Login Error</strong>:
                                            Could not create a new user account.
                                            Unknown error. [user: ' . htmlentities($userLogin,ENT_QUOTES,'UTF-8') . ', email: ' . htmlentities($userEmail,ENT_QUOTES,'UTF-8') . ']'));
                            }                        
                    }
                }
                else {
                    /*
                     * Did not pass dir auth, and no login present in WP
                     */
                    if($boolRestoreBlog) restore_current_blog();
                    do_action( 'wp_login_failed', $username );
                    return $userData;
                }
            }
            else {
                /*
                 * Dealing with an existing WP account
                 */
                if (!$loginUserIsDirUser) {
                    /*
                     * WP-only user
                     */
                    if ( wp_check_password($password, $login->user_pass, $login->ID) ) {
                        /*
                         * WP user, password okay.
                         */
                         if($boolRestoreBlog) restore_current_blog();
                         return new WP_User($login->ID);
                    } 
                    else {
                        /*
                         * WP user, wrong pass
                         */
                        if($boolRestoreBlog) restore_current_blog();
                        do_action( 'wp_login_failed', $username );
                        return new WP_Error('incorrect_password',__('<strong>WordPress Login Error</strong>:
                                    Incorrect password.'));
                    }
                }
                else {
                    /**
                     * Directory user, try ldap binding
                     */
                    $userData = wpDirAuth_auth($username,$password);
                    
                    if ( !is_wp_error($userData) ) {
                        /*
                         * Directory user, password okay.
                         */
                        wpDirAuth_remove_password_nag($login->ID); 
                        if($boolRestoreBlog) restore_current_blog();
                        return new WP_User($login->ID);
                    }
                    else {
                        /*
                         * Directory user, wrong pass
                         */
                        if($boolRestoreBlog) restore_current_blog();
                        do_action( 'wp_login_failed', $username );
                        return $userData;
                    }
                }
            }
        }
        else {
            /**
             * Directory auth == false
             */
            if (!$login || ($login->user_login != $username) ) {
                /**
                 * No existing account record found
                 */
                if($boolRestoreBlog) restore_current_blog();
                do_action( 'wp_login_failed', $username );
                return new WP_Error('failed_login',__('<strong>WordPress Login Error</strong>:
                            Could not authenticate user.
                            Please check your credentials.'));
            } 
            else {
                /*
                 * Found an existing WP account.
                 */
                if ( wp_check_password($password, $login->user_pass, $login->ID) ) {
                    /*
                     * WP user, password okay.
                     */
                    if($boolRestoreBlog) restore_current_blog();
                    return new WP_User($login->ID);
                } 
                else {
                    /*
                     * WP user, wrong pass
                     */
                    if($boolRestoreBlog) restore_current_blog();
                    do_action( 'wp_login_failed', $username );
                    return new WP_Error('incorrect_password',__('<strong>WordPress Login Error</strong>:
                                Incorrect password.'));
                }
            }
        }
    }

    
    /**
     * WordPress wp_setcookie overwrite.
     * Sets the WP session cookies.
     *
     * @param string $username Login form username.
     * @param string $password Login form password
     * @param boolean $already_md5 Has the pswd been double-hashed already?
     * @param string $home
     * @param string $siteurl
     * @param boolean $remember
     * @return void
     * 
     * @uses wpDirAuth_makeCookieMarker
     * 
     * @see http://codex.wordpress.org/Pluggable_Functions 
     */
    function wp_setcookie($username, $password, $already_md5 = false, $home = '', $siteurl = '', $remember = false) 
    {
        global $wpdb;

        /**
         * Try to locate the user's record and define if it is an existing directory user
         */
        $login = get_userdatabylogin($username);
        //$login = $wpdb->get_row('SELECT ID FROM $wpdb->users WHERE user_login = '$username'');
        $loginUserIsDirUser = ($login) ? get_usermeta($login->ID, 'wpDirAuthFlag') : 0;
        
        /**
         * Get wpsDirAuth options
         */
        $enable       = get_site_option('dirAuthEnable');
        $cookieMarker = get_site_option('dirAuthCookieMarker');
        
        if (!$cookieMarker) {
            $cookieMarker = wpDirAuth_makeCookieMarker();
        }
        
        /**
         * Set the password hash cookie
         */
        if (($enable) && ($loginUserIsDirUser)) {
            $password = md5($username).md5($cookieMarker);
        }
        else {
            if (!$already_md5) {
                $password = md5( md5($password) ); // Double hash the password in the cookie.
            }
        }
    
        /**
         * Updated WP remember me option for directory users to only be
         * remembered for 1 hour so that institutional passwords are not
         * overly endangered when accessing the blog from a public terminal.
         */
        if ( $remember ){
            $duration = ($loginUserIsDirUser) ? strtotime('1 hour') : strtotime('6 months');
            $expire = time() + $duration;
        }
        else {
            $expire = 0;
        }

        /**
         * The rest of the logic is from the original WP wp_setcookie
         * function, from /wp-inlcudes/pluggable.php version 2.2.2
         */
        if ( empty($home) )
            $cookiepath = COOKIEPATH;
        else
            $cookiepath = preg_replace('|https?://[^/]+|i', '', $home . '/' );
    
        if ( empty($siteurl) ) {
            $sitecookiepath = SITECOOKIEPATH;
            $cookiehash = COOKIEHASH;
        } else {
            $sitecookiepath = preg_replace('|https?://[^/]+|i', '', $siteurl . '/' );
            $cookiehash = md5($siteurl);
        }
    
        setcookie(USER_COOKIE, $username, $expire, $cookiepath, COOKIE_DOMAIN);
        setcookie(PASS_COOKIE, $password, $expire, $cookiepath, COOKIE_DOMAIN);
    
        if ( $cookiepath != $sitecookiepath ) {
            setcookie(USER_COOKIE, $username, $expire, $sitecookiepath, COOKIE_DOMAIN);
            setcookie(PASS_COOKIE, $password, $expire, $sitecookiepath, COOKIE_DOMAIN);
        }

    }

    /**
    * Prints data on a variable into a comments block in the source code of a page. Used for debugging purposes only.
    * 
    * @param mixed $mxdVar
    * @param string $strMsg
    */
    function wpDirAuthPrintDebug($mxdVar,$strMsg){
        echo PHP_EOL,'<!-- ',$strMsg,': ',PHP_EOL,var_export($mxdVar,true),PHP_EOL,'-->',PHP_EOL;
    }
    
    /**
    * Removes the "you're using a default password"" nag for dirauth accounts
    * 
    * @param integer $userID
    * @return void
    */
    function wpDirAuth_remove_password_nag($userID){
        if(get_user_option('default_password_nag',$userID)){
            update_user_option($userID, 'default_password_nag', false, true);
        }
    }
    
    /**
    * Retrieves values given in WPDIRAUTH_LDAP_RETURN_KEYS from a valid, bound LDAP connection 
    * 
    * @param resource $rscConnection verified LDAP connection resource
    * @param string $strBaseDn
    * @param string $strFilterQuery
    * @param mixed $rscReult if LDAP search was already performed. default null
    * @return mixed WP_Error object if there was an error encountered, otherwise an array of user details
    * 
    * @TODO right now it's actually coded such that what is returned is always the same, even if you change the keys in WPDIRAUTH_LDAP_RETURN_KEYS. Rewrite it so 
    * it will dynamically retrieve the values. idea is that WPDIRAUTH_LDAP_RETURN_KEYS would become an associative array of key names to return => LDAP keys to retrieve.
    * WPDIRAUTH_LDAP_RETURN_KEYS = serialize(array(
    *       'first_name'    =>'givenname',
    *       'last_name'     =>'sn',
    *       'email'         =>'mail'
    * ));
    * of course, we'll need to somehow require at least the email key since we need that one in order to add the user. 
    */
    function wpDirAuth_retrieveUserDetails($rscConnection,$strBaseDn,$strFilterQuery,$rscResult=NULL){
        //now that we have a valid connection and are bound, we need to find the user.
        
        if(is_null($rscResult)){
            $rscResult = ldap_search($rscConnection,$strBaseDn,$strFilterQuery,unserialize(WPDIRAUTH_LDAP_RETURN_KEYS)); 
        }
        
        /**
         * At this point, we will no longer use $strFilterQuery EXCEPT in the output of an error message.  To ensure we dont introduce an injection point, we will encode any 
         * html entities that might be present. 
         */
        $strFilterQuery = htmlentities($strFilterQuery,ENT_QUOTES,'UTF-8');
        
        if(!$rscResult){
            return new WP_Error ('noprofile_search', __('Directory authentication initially succeeded, but no valid profile was found (search procedure).')
                   ." [$strFilter]");                
        } else {
            $aryUserDetails = @ldap_get_entries($rscConnection, $rscResult);
            
            $intCount = intval($aryUserDetails['count']);
            if ($intCount < 1) {
                return new WP_Error ('noprofile_getentries', __('Directory authentication initially succeeded, but no valid profile was found ("get entries" procedure).')
                       ." [$strFilterQuery]");
            } elseif ($intCount > 1) {
                return new WP_Error ('not_unique', __('Directory authentication initially succeeded, but the username you sent is not a unique profile identifier.')
                       . " [$strFilterQuery]");
            } else {
                $strEmail       = isset($aryUserDetails[0]['mail'][0]) ? $aryUserDetails[0]['mail'][0] : '';
                
                $strLastName    = isset($aryUserDetails[0]['sn'][0]) ? $aryUserDetails[0]['sn'][0] : '';
                
                $strFirstName   = isset($aryUserDetails[0]['givenname'][0]) ? $aryUserDetails[0]['givenname'][0] : '';
    
                return array(
                    'email'      => $strEmail,
                    'last_name'  => $strLastName,
                    'first_name' => $strFirstName
                );            
            }
         }
    }
    
    /**
    * Handles connecting to LDAP and performing a search for the given SSOID
    * 
    * @param string $strSSOID
    * @return mixed WP_Error object on failure, array of user details on success
    * @TODO this function shares a LOT with wpDirAuth_auth. see if you cant combine them some more
    */
    function wpDirAuth_ConnectAndLookupUser($strSSOID){
        $boolFound = false;
        
        $strBaseDn           = get_site_option('dirAuthBaseDn');
        $strPreBindUser      = get_site_option('dirAuthPreBindUser','');
        $strPreBindPassword  = get_site_option('dirAuthPreBindPassword','');
        $strAccountSuffix    = get_site_option('dirAuthAccountSuffix');
        $strFilter           = get_site_option('dirAuthFilter');
        $intEnableSSL        = get_site_option('dirAuthEnableSsl');        
        
        if ($strAccountSuffix) $strSSOID .= $strAccountSuffix;
    
        if (!$strFilter || empty($strFilter)) $strFilter = WPDIRAUTH_DEFAULT_FILTER;
        
        $strFilterQuery = "($strFilter=$strSSOID)";        
        
        $aryControllers = wpDirAuth_shuffleControllers(explode(',', get_site_option('dirAuthControllers')));
        
        if(is_wp_error($aryControllers)){
            return $aryControllers; //there werent any controllers to connect to
        }
        
        /**
        * @todo if we get a successful connection, cant we break out of the loop before we go through binding and a search?  Or is it possible that one DC in the 
        * list might not allow anonymous searching and/or the pre-bind user/pass isnt valid on one of them and we need to try the next in the list?  
        */
        foreach($aryControllers as $strDC){
            $rscConnection = wpDirAuth_establishConnection($strDC,$intEnableSSL);
            if(is_wp_error($rscConnection)){
                return $rscConnection;  //tls failed to start on the DC
            }
            
            if(!wpDirAuth_bindTest($rscConnection,$strPreBindUser,$strPreBindPassword,$strBaseDn)){
                return new WP_Error('login_failed',__('<strong>Error Connecting to LDAP Server</strong><p>'
                    . 'There was an error connecting to your LDAP server ('.  htmlentities($strDC,ENT_QUOTES,'UTF-8').'). Please see the LDAP error message below for troubleshooting:</p>'
                    . ldap_error($rscConnection)));
            }
            
            //successfully bound, now we need to get the user details
            return wpDirAuth_retrieveUserDetails($rscConnection,$strBaseDn,$strFilterQuery);
        }
    }
    
    /**
    * Checks to make sure the user doesnt already exist and that the email associated with a SSOID isnt already in use in the blog, and if not, adds the user.
    * 
    * @param string $strSSOID Single Sign On ID
    * @param string $strRole Role chosen to give the new user
    * @return mixed array of user details on success or WP_Error object on failure
    */
    function wpDirAuth_add_new_user($strSSOID,$strRole,$intBlogID = 0){
        /**
        * We need to see if the user name already exists.  if not, then we need to see if the email address is already in use, if not, then we need to try and look
        * up the user.  then if we actually found something, then we'll add them into wordpress    
        */
        $strSSOID = sanitize_user($strSSOID);
        $intBlogID = is_int($intBlogID) ? $intBlogID : (int) $intBlogID;   //just to make sure
        
        if(username_exists($strSSOID)){
            echo '<p>user already exists</p>';
            return new WP_Error('username_exists',__('<p>Could not create a new Wordpress account because the directory username <strong>'
                            . htmlentities($strSSOID,ENT_QUOTES,'UTF-8') . '</strong> is already registered on this site.</p>'));   
        }
        
        //we'll have to go ahead and look them up in LDAP in order to check their email address
        $aryUserDetails = wpDirAuth_ConnectAndLookupUser($strSSOID);
        if(is_wp_error($aryUserDetails)){
            return $aryUserDetails;
        } 
        $strUserEmail = apply_filters('user_registration_email', $aryUserDetails['email']);
                 
        if(email_exists($strUserEmail)){
            echo '<p>Email address already exists</p>';
            return new WP_Error('email_exists',__('Could not create a new WP account because the email <strong>' 
                . htmlentities($strUserEmail,ENT_QUOTES,'UTF-8') . '</strong> is already registered with this site.'));
        }
        
        $aryUserDetails['user_pass'] = mt_rand();//we're going to store a random password in WP since directory users will never use it to log in anyway'
        $aryUserDetails['user_login'] = $strSSOID;
        $aryUserDetails['user_email'] = $aryUserDetails['email'] = $strUserEmail;
        /**
        * @TODO ask Stephen why he's replacing .'s with _'s in the user name of the email address. Does nickname not allow spaces?
        */
        $tmpAr = split('@',$aryUserDetails['email']);
        $aryUserDetails['nickname'] =  str_replace('.','_',$tmpAr[0]);
        $aryUserDetails['display_name'] = $aryUserDetails['first_name'].' '.$aryUserDetails['last_name'];
        $aryUserDetails['role'] = $strRole;        
        
        /**
        * Switch to the blog we want to insert the user into
        */
        if(defined('MULTISITE') && MULTISITE && function_exists('switch_to_blog')) switch_to_blog($intBlogID);
        $intUserID = wp_insert_user($aryUserDetails);
        
        if(!is_int($intUserID)){
            return new WP_Error('createuser_failed',__('For an unknow reason, WP failed to create a new user.'
                .' Failure occurred at line ' . __LINE__ . ' in the function ' . __FUNCTION__ . ' in the file ' . basename(__FILE__) . '.'));
        }
        
        $aryUserDetails['ID'] = $intUserID;
        update_usermeta($intUserID, 'wpDirAuthFlag', 1);
        wpDirAuth_remove_password_nag($intUserID);
        
        //for situations where an admin is adding a user from a site edit screen
        /**
        if($intBlogID != 0){
            add_user_to_blog($intBlogID,$intUserID,$strRole);
        }  */
        
        if(defined('MULTISITE') && MULTISITE && function_exists('switch_to_blog')) restore_current_blog();
        return $aryUserDetails;        
    }
    
    /**
    * Loops through the WP_Error object and prints out the error messages it contains
    * 
    * @param object $objError
    * @return void
    */
    function wpDirAuth_print_error_messages($objError){
        echo PHP_EOL,'<div class="error">',WPDIRAUTH_ERROR_TITLE,'<ul>',PHP_EOL;
        foreach($objError->get_error_messages() as $strErrMsg){
            echo '<li>',$strErrMsg,'</li>',PHP_EOL;
        }
        echo '</ul></div>',PHP_EOL;
    }
    
    /**
    * Constructs the message to be displayed when a new user has been added successfully
    * 
    * @param string $strSSOID User's Single Sign On ID
    * @param integer $strUserID user's wordpress user ID
    * @param array Data on the sites and roles that the user has been added to
    * @return string
    * @uses wpDirAuth_determine_A_or_An
    * 
    */
    function wpDirAuth_construct_success_msg($strSSOID,$strUserID,$arySitesData,$strExtraMsg=''){
        //$arySitesData = array('blogname','role','siteurl');
        $strMsg = '<div id="message" class="updated">
            <p>Just created user <strong><a href="user-edit.php?user_id=%d">%s</a></strong> as %s. %s
            </div>';
        
        $strSiteMessage = '%s for site <a href="%s">%s</a>';
        $arySiteMsgParts = array();
        foreach($arySitesData as $arySiteData){
            $arySiteMsgParts[] = sprintf($strSiteMessage,$arySiteData['role'],$arySiteData['siteurl'],$arySiteData['blogname']);           
        }
        
        return sprintf($strMsg,$strUserID,$strSSOID,implode($arySiteMsgParts,', '),$strExtraMsg);
    }
    
    /**
    * Just determines if the word $strWord should be prefaced with 'a' or 'an'.
    * Yea, i know it's picky, but I work with editors who complain about this type of stuff all the time  =P
    * 
    * @param string $strWord
    * @return string 
    */
    function wpDirAuth_determine_A_or_An($strWord){
        $strAorAn = 'a';
        if(in_array(substr($strWord,0,1),array('a','e','i','o','u'))){
            $strAorAn .= 'n';
        }
        
        return $strAorAn;        
    }
    
    /**
    * Adds contextual help to the Add Dir Auth page under the Users menu
    * 
    */
    function wpDirAuth_add_user_contextual_help(){
        $strMarketingSSOID = get_site_option('dirAuthMarketingSSOID','Username');
        add_contextual_help('users_page_'.basename(__FILE__,'.php'),
            '<p>' . __('To add a directory authenticated user from your institution to your site, fill in the form on this screen. If you&#8217;re not sure which role to assign, you can use the link below to review the different roles and their capabilities. Here is a basic overview of roles:') . '</p>' .
            '<ul>' .
                '<li>' . __('Administrators have access to all the administration features.') . '</li>' .
                '<li>' . __('Editors can publish posts, manage posts as well as manage other people&#8217;s posts, etc.')  . '</li>' .
                '<li>' . __('Authors can publish and manage their own posts.') . '</li>' .
                '<li>' . __('Contributors can write and manage their posts but not publish posts or upload media files.') . '</li>' .
                '<li>' . __('Subscribers can read comments/comment/receive newsletters, etc.') . '</li>' .
            '</ul>' .
            '<p>' . __('The user\'s insitutional single-sign-on ID (e.g. ' . $strMarketingSSOID .') will become the user\'s Wordpress username.') . '</p>' .
            '<p>' . __('New users will receive an email letting them know they&#8217;ve been added as a user for your site.') . '</p>' .
            '<p>' . __('Remember to click the Add User button at the bottom of this screen when you are finished.') . '</p>' .
            '<p><strong>' . __('For more information:') . '</strong></p>' .
            '<p>' . __('<a href="http://wordpress.org/support/" target="_blank">Support Forums</a>') . '</p>'
        );        
    }
    
    /**
    * Processes and outputs the Add Dir Auth user form.
    * @return void
    */
    function wpDirAuth_add_user_panel(){
        _log('WPDIRAUTH - function ' . __FUNCTION__ . ' activated. ');
        /**
        * Still needed?
        */
        global $id;
        /**
        * get_current_screen()->id = site-users-network will let us know if we are on the sites,edit,user tab
        */
        $strScreenID = get_current_screen()->id;
        /**
        * Are we running in a wordpress network and in the network area?
        */
        $boolIsNetworkAdminScreen = (is_network_admin() && $strScreenID != 'site-users-network') ? true : false; 
        /**
        * How do we refer to their SSOID?
        */
        $strMarketingSSOID = get_site_option('dirAuthMarketingSSOID','Username');

        $strReferer = wpDirAuth_get_referer();        
        
        /**
        * defaults
        */
        $strWpDirAuthSSOID = '';
        $strWpDirAuthRole = '';
        $boolConfirmationEmail = true;
        $objErrors = new WP_Error;
        $strSuccess = '';
        if($boolIsNetworkAdminScreen){
            $arySitesData = wpDirAuth_retrieve_multisite_blog_data();
        }
        
        if($_POST){
            if(wp_verify_nonce($_POST['_wpnonce_add-da-user'],'add-da-user')){
                /**
                * We gots a problem....  if they've checked all the boxes and chosen roles but forgot to enter the pawprint (it happens, you did
                * it yourself!) then we cant rebuild the list of which sites were checked/not checked later because we're jumping out before 
                * we get to the point where we build that data.
                */
                if(isset($_POST['ssoid']) && $_POST['ssoid'] == ''){
                    $objErrors->add('blank_ssoid',__('<p>'.$strMarketingSSOID.' can not be left blank.</p>'));
                } else {
                    $strWpDirAuthSSOID = wpDirAuth_sanitize($_POST['ssoid']);
                    
                    if($boolIsNetworkAdminScreen && $strReferer != 'site-users.php'){
                         $arySitesAndRoles = array();
                         $aryValidSiteIDs = array_keys($arySitesData);
                         $aryValidRoles = array_keys(get_editable_roles());
                         _log('contents of the post in function ' . __FUNCTION__.' at line ' . __LINE__ . ':'.PHP_EOL.var_export($_POST,true));
                        //we SHOULD have at least one site set.
                        for($i=0;$i<count($arySitesData);++$i){
                            $strPostSite = 'site'.$i;
                            $intCountPostSite = count($_POST[$strPostSite]);
                            /**
                            * We need to make sure that the site param is set, that it's an array and that it contains at least one element, but no more than
                            * two
                            */
                            if(isset($_POST[$strPostSite]) && is_array($_POST[$strPostSite]) && $intCountPostSite>0 && $intCountPostSite<3){
                                if($intCountPostSite == 1 && is_string(current($_POST[$strPostSite]))){
                                    /**
                                    *  If the array has only one element, then this site wasnt selected as one we want to add the user to.  but we
                                    *  need, for simplicity sake, to make the array contain two elements before we do input validation
                                    */
                                    $_POST[$strPostSite] = array('',current($_POST[$strPostSite]));
                                /**
                                * Since we know that the array has two elements, we'll test to make sure the siteid is valid' 
                                */
                                } elseif(!is_numeric($_POST[$strPostSite][0]) || !in_array($_POST[$strPostSite][0],$aryValidSiteIDs)) {
                                    $_POST[$strPostSite][0] = '';    
                                }
                                
                                /**
                                * 
                                */
                                if(!in_array($_POST[$strPostSite][1],$aryValidRoles)){
                                    $_POST[$strPostSite][1] = '';
                                }
                                
                                /**
                                * If we now have non-empty values for both elements, we'll add them to our array to be used for inserting the user into sites
                                */
                                if($_POST[$strPostSite][0] != '' && $_POST[$strPostSite][1] != ''){
                                    $arySitesAndRoles[$i] = array('blog_id'=>$_POST[$strPostSite][0],'role'=>$_POST[$strPostSite][1]);
                                }

                            }    
                        }

                    }
                    
                    $strWpDirAuthRole = (isset($_POST['role']) && in_array($_POST['role'],array_keys(get_editable_roles()))) ? $_POST['role'] : get_site_option('default_role');
                    $intBlogID = (isset($_POST['id']) && is_numeric($_POST['id'])) ? intval($_POST['id']) : '';
                    if(isset($_POST['noconfirmation']) && $_POST['noconfirmation'] == 1) $boolConfirmationEmail = false;
                                        
                    if(!isset($arySitesAndRoles) || !$boolIsNetworkAdminScreen){
                        $aryUserData = wpDirAuth_add_new_user($strWpDirAuthSSOID,$strWpDirAuthRole,$intBlogID);
                        _log('adding a standard user (' . $strWpDirAuthSSOID .')from either inside a site, or from the edit section of a site');
                        _log('user data from newly added user is : ' . PHP_EOL . var_export($aryUserData,true) . PHP_EOL);     
                    } elseif(count($arySitesAndRoles)<1) {
                        $aryUserData = new WP_Error('no_site_role_selected','<p>You will need to select at least one site to add this user to.</p>');    
                    } else {
                        $aryUserData = wpDirAuth_add_new_user_to_multi_sites($strWpDirAuthSSOID,$arySitesAndRoles);
                        _log('adding a user (' . $strWpDirAuthSSOID .')from the network Add Dir Auth user section');
                    }
                    
                    if(is_wp_error($aryUserData)){
                        //foreach($objErrors->)
                        
                        //$mxdErrors = $aryUserData;
                        $objErrors->add($aryUserData->get_error_code(),$aryUserData->get_error_message(),$aryUserData->get_error_data());
                    } else {
                        $arySitesAddedTo = array();
                        if(isset($arySitesAndRoles) && count($arySitesAndRoles)!=0){
                            foreach($arySitesAndRoles as $arySiteData){
                                $arySitesAddedTo[] = array(
                                                      'blogname'=>$arySitesData[$arySiteData['blog_id']],
                                                      'aoran'   =>wpDirAuth_determine_A_or_An($arySiteData['role']),
                                                      'role'    =>$arySiteData['role'],
                                                      'siteurl' =>get_site_url($arySiteData['blog_id'],'','https')    
                                    
                                                   );
                            }        
                        } else {
                            $arySitesAddedTo[] = array(
                                                'blogname'  =>get_site_option('blogname'),
                                                'aoran'     =>wpDirAuth_determine_A_or_An($strWpDirAuthRole),
                                                'role'      =>$strWpDirAuthRole,
                                                'siteurl'   =>site_url() 
                                                );    
                        } 
                        
                         /**
                         * ok, the admin has just successfully added a user to a site from the sites->edit->users tab.  Since we cant seem to
                         * redirect them back to the screen automatically, let's give them a link to go back.'
                         */
                         if($strReferer == 'site-users.php' && $boolIsNetworkAdminScreen){
                             $strReturnToURL = wp_get_referer();
                             $strExtraMessage = '<a href="'.$strReturnToURL.'">Return to the User tab</a> of the '. $arySitesData[$intBlogID] . ' site.';
                         } else {
                             $strExtraMessage = '';
                         }                        
                        
                        $strSuccess = wpDirAuth_construct_success_msg($strWpDirAuthSSOID,$aryUserData['ID'],$arySitesAddedTo,$strExtraMessage);
                        _log('for user ' . $strWpDirAuthSSOID . ', added them to ' . var_export($arySitesAddedTo,true).'.');                       
                        
                        if($boolConfirmationEmail){
                            foreach($arySitesAddedTo as $arySiteAddedToData){
                                $strMsg = sprintf(WPDIRAUTH_EMAIL_NEWUSER_NOTIFY,$arySiteAddedToData['blogname'],$arySiteAddedToData['aoran'],$arySiteAddedToData['role'],$strMarketingSSOID,$strWpDirAuthSSOID,$arySiteAddedToData['siteurl'].'/wp-login.php');
                                wp_mail($aryUserData['email'],'['.$arySiteAddedToData['blogname'].'] You\'ve been added!',$strMsg);
                            }
                        } 
                        
                        //reset back to defaults
                        $strWpDirAuthSSOID = '';
                        $strWpDirAuthRole = '';
                        $boolConfirmationEmail = true;
                    }
                } 
            } else {
                $objErrors->add('invalid-nonce',__('Invalid nonce value'));
            }
         }

         
         

?>
        <h3>Add New Directory Authentication User</h3>
<?php 
    if(count($objErrors->errors) != 0) {
        wpDirAuth_print_error_messages($objErrors);
    } elseif($strSuccess != ''){
        echo $strSuccess;
    } 
?>      <p><?php _e('Add a directory authenticated user to this site/network'); ?></p>
        <p><?php _e('Please note: Your LDAP/AD instance must allow anonymous profile searches, or you must provide a pre-bind account/password in the <a href="options-general.php?page='.basename(__FILE__).'">Directory Auth settings page.</a>') ?></p>
    
        <form action="<?php if(isset($strScreenID) && $strScreenID == 'site-users-network') echo 'users.php?page=wpDirAuth'; ?>" method="post" name="adddauser" id="createuser" class="add:users: validate"<?php do_action('user_new_form_tag');?>>
        <?php 
            if(isset($id) && $id != '' && is_multisite()){
                echo '<input type="hidden" name="id" value="',$id,'" />',PHP_EOL;
            }
        ?>
        <input name="action" type="hidden" value="add-da-user" />
        <?php wp_nonce_field( 'add-da-user', '_wpnonce_add-da-user' ); ?>
        <table class="form-table">
            <tr class="form-field form-required">
                <th scope="row">
                    <label for="ssoid"><?php _e($strMarketingSSOID.'/SSOID'); ?> <span class="description"><?php _e('(required)'); ?></span></label>
                </th>
                <td>
                    <input name="ssoid" type="text" id="ssoid" value="<?php echo esc_attr($strWpDirAuthSSOID); ?>" aria-required="true" />
                </td>
            </tr>
            <?php if($boolIsNetworkAdminScreen):?>
            <tr class="form-field">
                <th scope="row"><label for="blogs"><?php _e('Site');?></label></th>
                <th><label for="role"><?php _e('Role');?></label></th>
            </tr>
                <?php 

                    $i=0;
                    
                    foreach($arySitesData as $intSiteID=>$strSiteName){
                        $boolChecked = false;
                        
                        if(isset($arySitesAndRoles[$i])){
                            $aryFormSiteData = $arySitesAndRoles[$i];    
                        } elseif(isset($_POST['site'.$i])) {
                            $aryFormSiteData = $_POST['site'.$i];    
                        } else {
                            $aryFormSiteData = array();
                        }
                        
                        _log('aryFormSiteData at line ' . __LINE__ . ': ' . var_export($aryFormSiteData,true));
                        
                        /**
                        * We are working on the assumption that there are either ALWAYS two elements in aryformSiteData or the array is empty.
                        * If the first element in the array isnt empty, then the current site needs to be checked
                        */
                        if(reset($aryFormSiteData) != ''){
                            $boolChecked = true;
                        }
                        
                        /**
                        * If the last element (eg second, role) isnt empty, then we want to select it from the list
                        */
                        $strRoleSelected = (end($aryFormSiteData) != '') ? current($aryFormSiteData) : '';
                        
                        echo '<tr>
                            <td>
                                <input name="site'.$i.'[]" value="'.$intSiteID.'" id="blog_'.$intSiteID.'" type="checkbox"';
                        
                        if($boolChecked) echo ' checked="checked"';
                                
                        echo ' />&nbsp;&nbsp;'.$strSiteName.'
                            </td>
                            <td>
                                <select name="site'.$i.'[]" id="role_'.$intSiteID.'">';
                        wp_dropdown_roles($strRoleSelected);
                        echo PHP_EOL,'</select>
                        </td>
                    </tr>';
                    ++$i;
                    }
                    
                ?>
            <?php else: ?>
            <tr class="form-field">
                <th scope="row"><label for="role"><?php _e('Role'); ?></label></th>
                <td><select name="role" id="role">
                    <?php
                    $strCurrentRole = empty($strWpDirAuthRole) ? get_site_option('default_role') : $strWpDirAuthRole;
                    wp_dropdown_roles($strCurrentRole);
                    ?>
                    </select>
                </td>
            </tr>            
            <?php endif;?>
            <tr>
                <th scope="row"><label for="noconfirmation"><?php _e('Skip Confirmation Email') ?></label></th>
                <td><label for="noconfirmation"><input type="checkbox" name="noconfirmation" id="noconfirmation" value="1"  <?php checked(!$boolConfirmationEmail); ?> /> <?php _e( 'Add the user without sending them a confirmation email.' ); ?></label></td>
            </tr>
        </table>

        <?php submit_button( __( 'Add New User '), 'primary', 'createuser', true, array( 'id' => 'createusersub' ) ); ?>

        </form>
                                          
<?php
    } // end  wpDirAuth_add_user_panel() function
        
    /**
     * Add custom WordPress actions
     * 
     * @uses wpDirAuth_addMenu
     * @uses wpDirAuth_loginFormExtra
     * @uses wpDirAuth_profileUpdate
     * @uses wpDirAuth_add_user_contextual_help
     */
    if (function_exists('add_action')) {
        /**
         *ok, if we are in a multisite, we want to add the settings for wpDirAuth 
         * to the Network Admin area, but NOT the child sites. Otherwise, for 
         * single sites, we need to add an admin panel
         * 
         * Also for MUTLISITE, we need to add extra add user pages since we can
         * add users in multiple areas when we are running a MULTISITE
         */
        if(WPDIRAUTH_MULTISITE){
            add_action('network_admin_menu','wpDirAuth_addNetworkMenu');
            add_action('show_network_site_users_add_new_form', 'wpDirAuth_add_user_panel');
            //add_action('network_site_users_after_list_table', 'wpDirAuth_add_user_panel');
            add_action('network_admin_menu','wpDirAuth_network_adduser');            
        } else {
            add_action('admin_menu',     'wpDirAuth_addMenu');
        }
        
        /**
         * EVERYSITE, regardless of multi or not, needs the ability to add users 
         */
        add_action('admin_menu','wpDirAuth_add_users_page');
        
        add_action('login_form',     'wpDirAuth_loginFormExtra');
        add_action('profile_update', 'wpDirAuth_profileUpdate');
        
        add_action('admin_head-users_page_'.basename(__FILE__,'.php'),'wpDirAuth_add_user_contextual_help');
    }
    
    
    /**
     * Add custom WordPress filters
     * 
     * @uses wpDirAuth_hidePassFields
     */
    if (function_exists('add_filter')) {
        add_filter('show_password_fields', 'wpDirAuth_hidePassFields');
    }
    
    function wpDirAuth_add_users_page(){
        if(function_exists('add_users_page')){
            add_users_page(
                'Add Directory Authentication Users',
                'Add Directory Authenticated User',
                'administrator',
                basename(__FILE__),
                'wpDirAuth_add_user_panel'
            );        
        }         
    }
    
    /**
    * TESTING
    */
    function wpDirAuth_network_adduser(){
        add_submenu_page('users.php','Add Directory Authenticated User','Add Dir Auth User','add_users','wpDirAuth','wpDirAuth_add_user_panel');   
    }
    
    function wpDirAuth_addNetworkMenu(){
        add_submenu_page('settings.php','wpDirAuth Directory Authentication Options','wpDirAuth','manage_options','wpDirAuth_optionsPanel','wpDirAuth_optionsPanel');
    }
    
    function wpDirAuth_retrieve_multisite_blog_data(){
        global $wpdb;
        $aryBlogData = array();
        $arySites = $wpdb->get_results($wpdb->prepare('SELECT blog_id FROM '. $wpdb->prefix . 'blogs ORDER BY blog_id'));
        _log('result of arySites: '.PHP_EOL.var_export($arySites,true).PHP_EOL);
        foreach($arySites as $objSiteData){
            $intSiteKey = $objSiteData->blog_id;
            $strTableName = $wpdb->prefix;
            $strTableName .= ($intSiteKey != 1) ? $intSiteKey . '_' : '';
            $strTableName .= 'options';
            /**
            * Wonder if I should do wpdb->get_var here instead?
            */
            $arySiteData = $wpdb->get_results($wpdb->prepare("SELECT option_value from $strTableName WHERE option_name = 'blogname'"),ARRAY_A);
            $aryBlogData[$intSiteKey] = (isset($arySiteData[0]['option_value']) && $arySiteData[0]['option_value'] != '') ? $arySiteData[0]['option_value'] : 'Unable to Retrive Blog Name';
        }
        _log('aryBlogData:'.PHP_EOL.var_export($aryBlogData,true).PHP_EOL);
        return $aryBlogData;
    }
    
    /**
    * put your comment there...
    * 
    * @param string $strSSOID
    * @param array $aryBlogIDsRoles should be a nested array with the sub-array containing the keys 'blog_id' and 'role'
    * @return mixed WP_Error or user details upon success.
    */
    function wpDirAuth_add_new_user_to_multi_sites($strSSOID,$aryBlogIDsRoles){
        $mxdFirstElement = reset($aryBlogIDsRoles);//reset back to the beginning
        if(count($aryBlogIDsRoles)<1 || !$mxdFirstElement){
            return new WP_Error('blog_id_role_missing',__('<p>Could not create a new Wordpress account because data on blog id and role is '
                . 'missing.  Function: ' . __FUNCTION__.', line ' . __LINE__.'.</p>'));     
        } else {
            
            $aryFirstSite = current($aryBlogIDsRoles);
            $aryUserData = wpDirAuth_add_new_user($strSSOID,$aryFirstSite['role'],$aryFirstSite['blog_id']);
            if(is_wp_error($aryUserData)){
                return $aryUserData;
            } else {
                //we already added for the first site
                unset($aryBlogIDsRoles[key($aryBlogIDsRoles)]); 
                foreach($aryBlogIDsRoles as $arySiteDetails){
                    add_user_to_blog($arySiteDetails['blog_id'],$aryUserData['ID'],$arySiteDetails['role']);
                }
            }  
        
            return $aryUserData;
        }  
    }
    
    function wpDirAuth_get_referer(){
        $strReferer = basename(wp_get_referer());
        if(strpos($strReferer,'?') !== FALSE){
            $aryRefererParts = explode('?',$strReferer);
            $strReferer = $aryRefererParts[0];
        }
        
        return $strReferer;
    }
}

register_activation_hook(__FILE__, 'wpDirAuth_activation');

function wpDirAuth_activation($network_wide){
    if($network_wide){
        /**
         * Prior to v1.7.5, wpDirAuth options were stored in wp_options when 
         * network activated instead of wp_sitemeta.  To ensure that users dont
         * lose their settings when upgrading to 1.7.5 or newer, we'll check
         * to see if there are wpDirAuth options in wp_options when network 
         * activating.  If so, we'll copy them over to wp_sitemeta and then 
         * delete them from wp_options 
         */
        $mxdWpDirAuthEnable = get_option('dirAuthEnable');
        if(is_integer($mxdWpDirAuthEnable) && $mxdWpDirAuthEnable !== FALSE){
            foreach(unserialize(WPDIRAUTH_OPTIONS) as $strOption){
                update_site_option($strOption,  get_site_option($strOption));
                delete_option($strOption);
            }
        }
    }
}

if(!function_exists('_log')){
  /**
  * For logging debug messages into the debug log.
  * 
  * @param mixed $message
  */
  function _log( $message, $boolBackTraced = false ) {
      _wpdirauth_log($message,null,$boolBackTraced);
  }
}

if(!function_exists('_wpdirauth_log')){
  /**
  * For logging debug messages into the debug log.
  * 
  * @param mixed $mxdVariable variable we need to debug
  * @param $strPrependMessage message to include
  * @param boolean $boolBackTraced
  * @param array $aryDetails details for doing a mini backtrace instead of the full thing
  * 
  */
  function _wpdirauth_log( $mxdVariable, $strPrependMessage = null, $boolBackTraced = false, $aryDetails = array() ) {
    $boolBackTrace = false;
    if( WP_DEBUG === true ){
      $strMessage = 'WPDIRAUTH: ';
      
      if(count($aryDetails) > 0){
          if(isset($aryDetails['line'])){
              $strMessage .= 'At line number ' . $aryDetails['line'] . ' ';
          }
          
          if(isset($aryDetails['func'])){
              $strMessage .= 'inside of function ' . $aryDetails['func'] . ' ';
          }
          
          if(isset($aryDetails['file'])){
              $strMessage .= 'in file ' . $aryDetails['file'] .' ';
          }
          
          $strMessage .= PHP_EOL;
      }
      
      if(!is_null($strPrependMessage)) $strMessage .= $strPrependMessage.' ';
      
      if( is_array( $mxdVariable ) || is_object( $mxdVariable ) ){
         $strMessage .= PHP_EOL . var_export($mxdVariable,true);
      } else {
        $strMessage .= $mxdVariable;
      }
      
      if($boolBackTrace && $boolBackTraced){
          $aryBackTrace = debug_backtrace();
          
          $strMessage .= PHP_EOL.'Contents of backtrace:'.PHP_EOL.var_export($aryBackTrace,true).PHP_EOL;          
      }
      
      error_log($strMessage);
    }
  }
}

?>