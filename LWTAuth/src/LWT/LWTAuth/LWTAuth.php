<?php

namespace LWTAuth;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandExecutor;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\OfflinePlayer;

class LWTAuth extends PluginBase {
	

	

	const PREFIX = "&1[ServerAuth] ";
	


	const ERR_USER_NOT_REGISTERED = 0;


	const SUCCESS = 1;
	

	const ERR_WRONG_PASSWORD = 2;
	

	const ERR_USER_NOT_AUTHENTICATED = 3;
	

	const ERR_USER_ALREADY_AUTHENTICATED = 4;
	

	const ERR_USER_ALREADY_REGISTERED = 5;
	

	const ERR_PASSWORD_TOO_SHORT = 6;

	const ERR_PASSWORD_TOO_LONG = 7;
	

	const ERR_MAX_IP_REACHED = 8;
	

	const ERR_GENERIC = 9;
	

	const CANCELLED = 10;
	

	const TOO_MANY_ATTEMPTS = 11;
	

	private $auth_users = array();
	

	private $auth_attempts = array();
	
	/** @var Task $task MySQL task*/
	public $task;

    /** @var boolean $mysql Use mysql */
    public $mysql;
    
    /** @var \mysqli $datbase MySQLi instance */
    private $database;
    
    /** @var boolean $register_message Register Message status */
    private $register_message = true;
    
    /** @var boolean $login_message Login Message status */
    private $login_message = true;
    
    /** @var LWTAuth $object Plugin instance */
    private static $object = null;
    

    public static function getAPI(){
    	return self::$object;
    }
    
    public function onLoad(){
    	if(!(self::$object instanceof LWTAuth)){
    		self::$object = $this;
    	}
    }
    
    /**
     * Translate Minecraft colors
     * 
     * @param char $symbol Color symbol
     * @param string $message The message to be translated
     * 
     * @return string The translated message
     */
    public function translateColors($symbol, $message){
    
    	$message = str_replace($symbol."0", TextFormat::BLACK, $message);
    	$message = str_replace($symbol."1", TextFormat::DARK_BLUE, $message);
    	$message = str_replace($symbol."2", TextFormat::DARK_GREEN, $message);
    	$message = str_replace($symbol."3", TextFormat::DARK_AQUA, $message);
    	$message = str_replace($symbol."4", TextFormat::DARK_RED, $message);
    	$message = str_replace($symbol."5", TextFormat::DARK_PURPLE, $message);
    	$message = str_replace($symbol."6", TextFormat::GOLD, $message);
    	$message = str_replace($symbol."7", TextFormat::GRAY, $message);
    	$message = str_replace($symbol."8", TextFormat::DARK_GRAY, $message);
    	$message = str_replace($symbol."9", TextFormat::BLUE, $message);
    	$message = str_replace($symbol."a", TextFormat::GREEN, $message);
    	$message = str_replace($symbol."b", TextFormat::AQUA, $message);
    	$message = str_replace($symbol."c", TextFormat::RED, $message);
    	$message = str_replace($symbol."d", TextFormat::LIGHT_PURPLE, $message);
    	$message = str_replace($symbol."e", TextFormat::YELLOW, $message);
    	$message = str_replace($symbol."f", TextFormat::WHITE, $message);
    	$message = str_replace($symbol."g", TextFormat::PINK, $message);
    
    	$message = str_replace($symbol."k", TextFormat::OBFUSCATED, $message);
    	$message = str_replace($symbol."l", TextFormat::BOLD, $message);
    	$message = str_replace($symbol."m", TextFormat::STRIKETHROUGH, $message);
    	$message = str_replace($symbol."n", TextFormat::UNDERLINE, $message);
    	$message = str_replace($symbol."o", TextFormat::ITALIC, $message);
    	$message = str_replace($symbol."r", TextFormat::RESET, $message);
    
    	return $message;
    }
    
    /**
     * Replace arrays in message
     *
     * @param string $message The message
     * @param array $array The values to replace
     *
     * @return string the message
     */
    public function replaceArrays($message, $array){
    	foreach($array as $key => $value){
    		$message = str_replace("{" . strtoupper($key) . "}", $value, $message);
    	}
    	return $message;
    }
    
    /**
     * Check MySQL database status
     * 
     * @param string $host MySQL host
     * @param string $port MySQL port
     * @param string $username MySQL username
     * @param string $password MySQL password
     * 
     * @return array true on success or false on error + error details
     */
    public function checkDatabase($host, $port, $username, $password){
    	$status = array();
    	$db = @new \mysqli($host, $username, $password, null, $port);
    	if($db->connect_error){
    		$status[0] = false;
    		$status[1] = $db->connect_error;
    		return $status;
    	}else{
    		$db->close();
    		$status[0] = true;
    		$status[1] = "Success!";
    		return $status;
    	}
    }
	
    /**
     * Initialize MySQL database connection
     * 
     * @param string $host
     * @param int $port
     * @param string $username
     * @param string $password
     * @param string $database
     * @param string $table_prefix
     * 
     * @return boolean true on SUCCESS, false on error
     */
    public function initializeDatabase($host, $port, $username, $password, $database, $table_prefix){
    	$db = @new \mysqli($host, $username, $password, null, $port);
    	if($db->connect_error){
    		return false;
    	}else{
    		$query = "CREATE DATABASE " . $database;
    		if ($db->query($query) == true) {
    			$db->select_db($database);
    			//Create Tables
    			if(\mysqli_num_rows($db->query("SHOW TABLES LIKE '" . $table_prefix . "serverauth'")) == 0){
    				$query = "CREATE TABLE " . $table_prefix . "lwtauth (version VARCHAR(50), api_version VARCHAR(50), password_hash VARCHAR(50))";
    				$db->query($query);
    			}
    			if(\mysqli_num_rows($db->query("SHOW TABLES LIKE '" . $table_prefix . "serverauthdata'")) == 0){
    				$query = "CREATE TABLE " . $table_prefix . "lwtauthdata (user VARCHAR(50), password VARCHAR(200), ip VARCHAR(50), firstlogin VARCHAR(50), lastlogin VARCHAR(50))";
    				$db->query($query);
    			}
    		    //Initialize Tables
    		    if(\mysqli_num_rows($db->query("SELECT version, api_version FROM " . $table_prefix . "lwtauth")) == 0){
    				$query = "INSERT INTO " . $table_prefix . "lwtauth (version, api_version, password_hash) VALUES ('" . $this->getVersion() . "', '" . $this->getAPIVersion() . "', '" . $this->getPasswordHash() . "')";
    				$db->query($query);
    			}else{
    				$query = "UPDATE " . $table_prefix . "lwtauth SET version='" . $this->getVersion() . "', api_version='" . $this->getAPIVersion() . "', password_hash='" . $this->getPasswordHash() . "' LIMIT 1";
    				$db->query($query);
    			}
    		}else{
    			$db->select_db($database);
    			//Create Tables
    			if(\mysqli_num_rows($db->query("SHOW TABLES LIKE '" . $table_prefix . "serverauth'")) == 0){
    				$query = "CREATE TABLE " . $table_prefix . "lwtauth (version VARCHAR(50), api_version VARCHAR(50), password_hash VARCHAR(50))";
    				$db->query($query);
    			}
    			if(\mysqli_num_rows($db->query("SHOW TABLES LIKE '" . $table_prefix . "serverauthdata'")) == 0){
    				$query = "CREATE TABLE " . $table_prefix . "lwtauthdata (user VARCHAR(50), password VARCHAR(200), ip VARCHAR(50), firstlogin VARCHAR(50), lastlogin VARCHAR(50))";
    				$db->query($query);
    			}
    		    //Initialize Tables
    		    if(\mysqli_num_rows($db->query("SELECT version, api_version FROM " . $table_prefix . "lwtauth")) == 0){
                    $query = "INSERT INTO " . $table_prefix . "lwtauth (version, api_version, password_hash) VALUES ('" . $this->getVersion() . "', '" . $this->getAPIVersion() . "', '" . $this->getPasswordHash() . "')";
    				$db->query($query);
    			}else{
    				$query = "UPDATE " . $table_prefix . "lwtauth SET version='" . $this->getVersion() . "', api_version='" . $this->getAPIVersion() . "', password_hash='" . $this->getPasswordHash() . "' LIMIT 1";
    				$db->query($query);
    			}
    		}
    		$this->database = $db;
    	}
    }
    
    /**
     * Search string in yml files
     * 
     * @param string $path Search path
     * @param string $str The string to search
     * 
     * @return int $count The number of occurrencies
     */
    private function grep($path, $str){
    	$count = 0;
    	foreach(glob($path . "*.yml") as $filename){
    		foreach(file($filename) as $fli=>$fl){
    			if(strpos($fl, $str) !== false){
    				$count += 1;
    			}
    		}
    	}
    	return $count;
    }
    
    public function onEnable(){
	    @mkdir($this->getDataFolder());
	    @mkdir($this->getDataFolder() . "users/");
	    @mkdir($this->getDataFolder() . "languages/");
	    //Save Languages
	    foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->getFile() . "resources/languages")) as $resource){
	    	$resource = str_replace("\\", "/", $resource);
	    	$resarr = explode("/", $resource);
	    	if(substr($resarr[count($resarr) - 1], strrpos($resarr[count($resarr) - 1], '.') + 1) == "yml"){
	    		$this->saveResource("languages/" . $resarr[count($resarr) - 1]);
	    	}
	    }
        $this->saveDefaultConfig();
        $this->cfg = $this->getConfig()->getAll();
        $this->getCommand("serverauth")->setExecutor(new Commands\Commands($this));
        $this->getCommand("register")->setExecutor(new Commands\Register($this));
        $this->getCommand("login")->setExecutor(new Commands\Login($this));
        $this->getCommand("logout")->setExecutor(new Commands\Logout($this));
        $this->getCommand("changepassword")->setExecutor(new Commands\ChangePassword($this));
        $this->getCommand("unregister")->setExecutor(new Commands\Unregister($this));
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new Tasks\MessageTask($this), 20);
        $this->task = $this->getServer()->getScheduler()->scheduleRepeatingTask(new Tasks\MySQLTask($this), 20);
        $this->mysql = false;
        //Check MySQL
        if($this->cfg["use-mysql"] == true){
        	$check = $this->checkDatabase($this->cfg["mysql"]["host"], $this->cfg["mysql"]["port"], $this->cfg["mysql"]["username"], $this->cfg["mysql"]["password"]);
        	if($check[0]){
        		$this->initializeDatabase($this->cfg["mysql"]["host"], $this->cfg["mysql"]["port"], $this->cfg["mysql"]["username"], $this->cfg["mysql"]["password"], $this->cfg["mysql"]["database"], $this->cfg["mysql"]["table_prefix"]);
        		Server::getInstance()->getLogger()->info($this->translateColors("&", LWTAuth::PREFIX . LWTAuth::getAPI()->getConfigLanguage()->getAll()["mysql-success"]));
        		$this->mysql = true;
        	}else{
        		Server::getInstance()->getLogger()->info($this->translateColors("&", LWTAuth::PREFIX . LWTAuth::getAPI()->replaceArrays(ServerAuth::getAPI()->getConfigLanguage()->getAll()["mysql-fail"], array("MYSQL_ERROR" => $check[1]))));
        	}
        }
    }
    
    //API Functions
    


    
    /**
     * Get the current MySQL database instance
     * 
     * @return mysqli|boolean
     */
    public function getDatabase(){
    	if($this->database instanceof \mysqli){
    		return $this->database;
    	}else{
    		return false;
    	}
    }
    
    /**
     * Get LWTAuth database configuration
     * 
     * @return array
     */
    public function getDatabaseConfig(){
    	return $this->getConfig()->getAll()["mysql"];
    }
    
    /**
     * Get LWTAuth data provider
     *
     * @return boolean true if LWTAuth is using MySQL, false if LWTAuth is using YAML config
     */
    public function getDataProvider(){
    	return $this->mysql;
    }
    
    /**
     * Check if register messages are enabled
     * 
     * @return boolean
     */
    public function areRegisterMessagesEnabled(){
    	return $this->register_message;
    }
    
    /**
     * Enable\Disable register messages
     * 
     * @param boolean $bool
     */
    public function enableRegisterMessages($bool = true){
    	if(is_bool($bool)){
    		$this->register_message = $bool;
    	}else{
    		$this->register_message = true;
    	}
    }
    
    /**
     * Check if login messages are enabled
     *
     * @return boolean
     */
    public function areLoginMessagesEnabled(){
    	return $this->login_message;
    }
    
    /**
     * Enable\Disable login messages
     *
     * @param boolean $bool
     */
    public function enableLoginMessages($bool = true){
    	if(is_bool($bool)){
    		$this->login_message = $bool;
    	}else{
    		$this->login_message = true;
    	}
    }
    
    /**
     * Get player data
     *
     * @param string $player
     *
     * @return array|int the array of player data on SUCCESS, otherwise the current error
     */
    public function getPlayerData($player){
    	if($this->isPlayerRegistered($player)){
    		if($this->getDataProvider()){
    			//Check MySQL connection
    			if($this->getDatabase() && $this->getDatabase()->ping()){
    				$query = "SELECT user, password, ip, firstlogin, lastlogin FROM " . $this->getDatabaseConfig()["table_prefix"] . "lwtauthdata WHERE user='" . strtolower($player) . "'";
    				if($this->getDatabase()->query($query)){
    					$data = array(
    						"password" => $this->getDatabase()->query($query)->fetch_assoc()["password"],
    						"ip" => $this->getDatabase()->query($query)->fetch_assoc()["ip"],
    						"firstlogin" => $this->getDatabase()->query($query)->fetch_assoc()["firstlogin"],
                            "lastlogin" => $this->getDatabase()->query($query)->fetch_assoc()["lastlogin"]
    					);
    					return $data;
    				}else{
    					return LWTAuth::ERR_GENERIC;
    				}
    			}else{
    				return LWTAuth::ERR_GENERIC;
    			}
    		}else{
    			$cfg = new Config($this->getDataFolder() . "users/" . strtolower($player . ".yml"), Config::YAML);
    			return $cfg->getAll();
    		}
    	}else{
    		return $this->isPlayerRegistered($player);	
    	}
    }
    
    /**
     * Get LWTAuth password hash
     * 
     * @return string
     */
    public function getPasswordHash(){
    	$cfg = $this->getConfig()->getAll();
    	return $cfg["passwordHash"];
    }
    
    /**
     * Get language data
     * 
     * @param string $language
     * 
     * @return \pocketmine\utils\Config
     */
    public function getLanguage($language){
    	if(file_exists($this->getDataFolder() . "languages/" . $language . ".yml")){
    		return new Config($this->getDataFolder() . "languages/" . $language . ".yml", Config::YAML);
    	}elseif(file_exists($this->getDataFolder() . "languages/EN_en.yml")){
    		return new Config($this->getDataFolder() . "languages/EN_en.yml", Config::YAML);
    	}else{
    		@mkdir($this->getDataFolder() . "languages/");
    		$this->saveResource("languages/EN_en.yml");
    		return new Config($this->getDataFolder() . "languages/EN_en.yml", Config::YAML);
    	}
    }
    
    /**
     * Get the LWTAuth language specified in config
     * 
     * @return \pocketmine\utils\Config
     */
    public function getConfigLanguage(){
    	$cfg = $this->getConfig()->getAll();
    	return $this->getLanguage($cfg["language"]);
    }
    
    /**
     * Check if a player is registered
     * 
     * @param string $player
     * 
     * @return boolean|int true or false on SUCCESS, otherwise the current error
     */
    public function isPlayerRegistered($player){
    	if($this->getDataProvider()){
    		//Check MySQL connection
    		if($this->getDatabase() && $this->getDatabase()->ping()){
    			if(\mysqli_num_rows($this->getDatabase()->query("SELECT user, password, ip, firstlogin, lastlogin FROM " . $this->getDatabaseConfig()["table_prefix"] . "lwtauthdata WHERE user='" . strtolower($player) . "'")) == 0){
    				return false;
    			}else{
    				return true;
    			}
    		}else{
    			return LWTAuth::ERR_GENERIC;
    		}
    	}else{
    		return file_exists($this->getDataFolder() . "users/" . strtolower($player . ".yml"));
    	}
    }
    
    /**
     * Check if a player is authenticated
     * 
     * @param Player $player
     * 
     * @return boolean
     */
    public function isPlayerAuthenticated(Player $player){
    	return isset($this->auth_users[strtolower($player->getName())]);
    }
    
    /**
     * Register a player to LWTAuth
     * 
     * @param Player $player
     * @param string $password
     * 
     * @return int|boolean true on SUCCESS, otherwise the current error
     */
    public function registerPlayer(Player $player, $password){
    	$cfg = $this->getConfig()->getAll();
    	if($this->isPlayerRegistered($player->getName())){
    		return LWTAuth::ERR_USER_ALREADY_REGISTERED;
    	}else{
    		if(strlen($password) <= $cfg["minPasswordLength"]){
    			return LWTAuth::ERR_PASSWORD_TOO_SHORT;
    		}elseif(strlen($password) >= $cfg["maxPasswordLength"]){
    			return LWTAuth::ERR_PASSWORD_TOO_LONG;
    		}else{
    			$this->getServer()->getPluginManager()->callEvent($event = new Events\LWTAuthRegisterEvent($player, $password));
    			if($event->isCancelled()){
    				return LWTAuth::CANCELLED;
    			}
    			if($this->getDataProvider()){
    				//Check MySQL connection
    				if($this->getDatabase() && $this->getDatabase()->ping()){
    					if($cfg["register"]["enable-max-ip"]){
    						if(\mysqli_num_rows($this->getDatabase()->query("SELECT user, password, ip, firstlogin, lastlogin FROM " . $this->getDatabaseConfig()["table_prefix"] . "lwtauthdata WHERE ip='" . $player->getAddress() . "'")) + 1 <= $cfg["register"]["max-ip"]){
    							$query = "INSERT INTO " . $this->getDatabaseConfig()["table_prefix"] . "lwtauthdata (user, password, ip, firstlogin, lastlogin) VALUES ('" . $player->getName() . "', '" . hash($this->getPasswordHash(), $password) . "', '" . $player->getAddress() . "', '" . $player->getFirstPlayed() . "', '" . $player->getLastPlayed() . "')";
    							if($this->getDatabase()->query($query)){
    								return LWTAuth::SUCCESS;
    							}else{
    								return LWTAuth::ERR_GENERIC;
    							}
    						}else{
    							return LWTAuth::ERR_MAX_IP_REACHED;
    						}
    					}else{
    						$query = "INSERT INTO " . $this->getDatabaseConfig()["table_prefix"] . "lwtauthdata (user, password, ip, firstlogin, lastlogin) VALUES ('" . $player->getName() . "', '" . hash($this->getPasswordHash(), $password) . "', '" . $player->getAddress() . "', '" . $player->getFirstPlayed() . "', '" . $player->getLastPlayed() . "')";
    						if($this->getDatabase()->query($query)){
    							return LWTAuth::SUCCESS;
    						}else{
    							return LWTAuth::ERR_GENERIC;
    						}
    					}
    				}else{
    					return LWTAuth::ERR_GENERIC;
    				}
    			}else{
    				if($cfg["register"]["enable-max-ip"]){
    					if($this->grep($this->getDataFolder() . "users/", $player->getAddress()) + 1 <= $cfg["register"]["max-ip"]){
    						$data = new Config($this->getDataFolder() . "users/" . strtolower($player->getName() . ".yml"), Config::YAML);
    						$data->set("password", hash($this->getPasswordHash(), $password));
    						$data->set("ip", $player->getAddress());
    						$data->set("firstlogin", $player->getFirstPlayed());
    						$data->set("lastlogin", $player->getLastPlayed());
    						$data->save();
    						return LWTAuth::SUCCESS;
    					}else{
    						return LWTAuth::ERR_MAX_IP_REACHED;
    					}
    				}else{
    					$data = new Config($this->getDataFolder() . "users/" . strtolower($player->getName() . ".yml"), Config::YAML);
    					$data->set("password", hash($this->getPasswordHash(), $password));
    					$data->set("ip", $player->getAddress());
    					$data->set("firstlogin", $player->getFirstPlayed());
    					$data->set("lastlogin", $player->getLastPlayed());
    					$data->save();
    					return LWTAuth::SUCCESS;
    				}
    			}
    		}
    	}
    }
    
	/**
	 * Unregister a player
	 * 
	 * @param Player|OfflinePlayer $player
	 * 
	 * @return int|boolean true on SUCCESS or false if the player is not registered, otherwise the current error
	 */
    public function unregisterPlayer($player){
    	if($player instanceof Player || $player instanceof OfflinePlayer){
    		if($this->isPlayerRegistered($player->getName())){
    			$this->getServer()->getPluginManager()->callEvent($event = new Events\ServerAuthUnregisterEvent($player));
    			if($event->isCancelled()){
    				return LWTAuth::CANCELLED;
    			}
    			if($this->getDataProvider()){
    				//Check MySQL connection
    				if($this->getDatabase() && $this->getDatabase()->ping()){
    					$query = "DELETE FROM " . $this->getDatabaseConfig()["table_prefix"] . "lwtauthdata WHERE user='" . strtolower($player->getName()) . "'";
    					if($this->getDatabase()->query($query)){
    						//Restore default messages
    						LWTAuth::getAPI()->enableLoginMessages(true);
    						LWTAuth::getAPI()->enableRegisterMessages(true);
    						return LWTAuth::SUCCESS;
    					}else{
    						return LWTAuth::ERR_GENERIC;
    					}
    				}else{
    					return LWTAuth::ERR_GENERIC;
    				}
    			}else{
    				@unlink($this->getDataFolder() . "users/" . strtolower($player->getName() . ".yml"));
    				//Restore default messages
    				LWTAuth::getAPI()->enableLoginMessages(true);
    				LWTAuth::getAPI()->enableRegisterMessages(true);
    				return LWTAuth::SUCCESS;
    			}
    		}else{
    			return LWTAuth::ERR_USER_NOT_REGISTERED;
    		}
    	}else{
    		return -1;
    	}
    }
    
    /**
     * Authenticate a Player
     * 
     * @param Player $player
     * @param string $password
     * @param boolean $hash
     * 
     * @return int|boolean true on SUCCESS, otherwise the current error
     */
    public function authenticatePlayer(Player $player, $password, $hash = true){
    	if($hash){
    		$password = hash($this->getPasswordHash(), $password);
    	}
    	if($this->isPlayerRegistered($player->getName())){
    		if(!$this->isPlayerAuthenticated($player)){
    			$this->getServer()->getPluginManager()->callEvent($event = new Events\LWTAuthAuthenticateEvent($player));
    			if($event->isCancelled()){
    				return LWTAuth::CANCELLED;
    			}
    			$cfg = $this->getConfig()->getAll();
    			if($this->getDataProvider()){
    				//Check MySQL connection
    				if($this->getDatabase() && $this->getDatabase()->ping()){
    					$query = "SELECT user, password, ip, firstlogin, lastlogin FROM " . $this->getDatabaseConfig()["table_prefix"] . "lwtauthdata WHERE user='" . strtolower($player->getName()) . "'";
    					$db_password = $this->getDatabase()->query($query)->fetch_assoc()["password"];
    					if($db_password){
    						if($password == $db_password){
    							$query = "UPDATE " . $this->getDatabaseConfig()["table_prefix"] . "lwtauthdata SET ip='" . $player->getAddress() . "', lastlogin='" . $player->getLastPlayed() . "' WHERE user='" . strtolower($player->getName()) . "'";
    							if($this->getDatabase()->query($query)){
    								$this->auth_users[strtolower($player->getName())] = "";
    								if($cfg['login']['enable-failed-logins-kick'] && isset($this->auth_attempts[strtolower($player->getName())])){
    									unset($this->auth_attempts[strtolower($player->getName())]);
    								}
    								return LWTAuth::SUCCESS;
    							}else{
    								return LWTAuth::ERR_GENERIC;
    							}
    						}else{
    							if($cfg['login']['enable-failed-logins-kick']){
    								if(isset($this->auth_attempts[strtolower($player->getName())])){
    									$this->auth_attempts[strtolower($player->getName())]++;
    								}else{
    									$this->auth_attempts[strtolower($player->getName())] = 1;
    								}
    								if($this->auth_attempts[strtolower($player->getName())] >= $cfg['login']['max-login-attempts']){
    									$player->close("", $this->translateColors("&", ServerAuth::getAPI()->getConfigLanguage()->getAll()["login"]["too-many-attempts"]));
    									unset($this->auth_attempts[strtolower($player->getName())]);
    									return LWTAuth::TOO_MANY_ATTEMPTS;
    								}
    							}
    							return LWTAuth::ERR_WRONG_PASSWORD;
    						}
    					}else{
    						return LWTAuth::ERR_GENERIC;
    					}
    				}else{
    					return LWTAuth::ERR_GENERIC;
    				}
    			}else{
    				$data = new Config($this->getDataFolder() . "users/" . strtolower($player->getName() . ".yml"), Config::YAML);
    				if($password == $data->get("password")){
    					$data->set("ip", $player->getAddress());
    					$data->set("lastlogin", $player->getLastPlayed());
    					$data->save();
    					$this->auth_users[strtolower($player->getName())] = "";
    					if($cfg['login']['enable-failed-logins-kick'] && isset($this->auth_attempts[strtolower($player->getName())])){
    						unset($this->auth_attempts[strtolower($player->getName())]);
    					}
    					return LWTAuth::SUCCESS;
    				}else{
    					if($cfg['login']['enable-failed-logins-kick']){
    						if(isset($this->auth_attempts[strtolower($player->getName())])){
    							$this->auth_attempts[strtolower($player->getName())]++;
    						}else{
    							$this->auth_attempts[strtolower($player->getName())] = 1;
    						}
    						if($this->auth_attempts[strtolower($player->getName())] >= $cfg['login']['max-login-attempts']){
    							$player->close("", $this->translateColors("&", LWTAuth::getAPI()->getConfigLanguage()->getAll()["login"]["too-many-attempts"]));
    							unset($this->auth_attempts[strtolower($player->getName())]);
    							return LWTAuth::TOO_MANY_ATTEMPTS;
    						}
    					}    					
    					return LWTAuth::ERR_WRONG_PASSWORD;
    				}
    			}
    		}else{
    			return LWTAuth::ERR_USER_ALREADY_AUTHENTICATED;
    		}
    	}else{
    		return $this->isPlayerRegistered($player->getName());
    	}
    }
    
    /**
     * Deauthenticate a player
     * 
     * @param Player $player
     * 
     * @return int|boolean true on SUCCESS, otherwise the current error
     */
    public function deauthenticatePlayer(Player $player){
    	if($this->isPlayerRegistered($player->getName())){
    		if($this->isPlayerAuthenticated($player)){
    			$this->getServer()->getPluginManager()->callEvent($event = new Events\LWTAuthDeauthenticateEvent($player));
    			if($event->isCancelled()){
    				return LWTAuth::CANCELLED;
    			}
    			if($this->getDataProvider()){
    				//Check MySQL connection
    				if($this->getDatabase() && $this->getDatabase()->ping()){
    					$query = "UPDATE " . $this->getDatabaseConfig()["table_prefix"] . "lwtauthdata SET ip='" . $player->getAddress() .  "' WHERE user='" . strtolower($player->getName()) . "'";
    					//Restore default messages
    					LWTAuth::getAPI()->enableLoginMessages(true);
    					LWTAuth::getAPI()->enableRegisterMessages(true);
    					unset($this->auth_users[strtolower($player->getName())]);
    					if($this->getDatabase()->query($query)){
    						return LWTAuth::SUCCESS;
    					}else{
    						return LWTAuth::ERR_GENERIC;
    					}
    				}else{
    					return LWTAuth::ERR_GENERIC;
    				}
    			}else{
    				$data = new Config($this->getDataFolder() . "users/" . strtolower($player->getName() . ".yml"), Config::YAML);
    				$data->set("ip", $player->getAddress());
    				$data->save();
    				//Restore default messages
    				LWTAuth::getAPI()->enableLoginMessages(true);
    				LWTAuth::getAPI()->enableRegisterMessages(true);
    				unset($this->auth_users[strtolower($player->getName())]);
    				return LWTAuth::SUCCESS;
    			}
    		}else{
    			return LWTAuth::ERR_USER_NOT_AUTHENTICATED;
    		}
    	}else{
    		return $this->isPlayerRegistered($player->getName());
    	}
    }
    
	/**
	 * Change player password
	 * 
	 * @param Player $player
	 * @param string $new_password
	 * 
	 * @return int|boolean true on SUCCESS or false if the player is not registered, otherwise the current error
	 */
    public function changePlayerPassword(Player $player, $new_password){
    	$cfg = $this->getConfig()->getAll();
    	if($this->isPlayerRegistered($player->getName())){
    		if(strlen($new_password) < $cfg["minPasswordLength"]){
    			return LWTAuth::ERR_PASSWORD_TOO_SHORT;
    		}elseif(strlen($new_password) > $cfg["maxPasswordLength"]){
    			return LWTAuth::ERR_PASSWORD_TOO_LONG;
    		}else{
    			$this->getServer()->getPluginManager()->callEvent($event = new Events\LWTAuthPasswordChangeEvent($player, $new_password));
    			if($event->isCancelled()){
    				return LWTAuth::CANCELLED;
    			}
    			if($this->getDataProvider()){
    				//Check MySQL connection
    				if($this->getDatabase() && $this->getDatabase()->ping()){
    					$query = "UPDATE " . $this->getDatabaseConfig()["table_prefix"] . "lwtauthdata SET password='" . hash($this->getPasswordHash(), $new_password) . "' WHERE user='" . strtolower($player->getName()) . "'";
    					if($this->getDatabase()->query($query)){
    						return LWTAuth::SUCCESS;
    					}else{
    						return LWTAuth::ERR_GENERIC;
    					}
    				}else{
    					return LWTAuth::ERR_GENERIC;
    				}
    			}else{
    				$data = new Config($this->getDataFolder() . "users/" . strtolower($player->getName() . ".yml"), Config::YAML);
    				$data->set("password", hash($this->getPasswordHash(), $new_password));
    				$data->save();
    				return LWTAuth::SUCCESS;
    			}	
    		}
    	}else{
    		return $this->isPlayerRegistered($player->getName());
    	}
    }
    
}
?>
