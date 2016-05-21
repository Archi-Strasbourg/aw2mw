<?php
namespace AW2MW;

use Symfony\Component\Yaml\Yaml;

define('CONFIG_FILE', __DIR__.'/../config.php');

class Config
{
    private static $instance;
    public $apiUrl = '';
    public $userSecret = '';
    public $salt = '';
    public $admin = array();
    /**
     * Config constructor
     */
    private function __construct($prod = false)
    {
        if ($prod) {
            $yamlfile = __DIR__.'/../config_prod.yml';
        } else {
            $yamlfile = __DIR__.'/../config.yml';
        }
        $yaml = Yaml::parse(file_get_contents($yamlfile));
        foreach ($yaml as $param => $value) {
            if (isset($this->$param)) {
                $this->$param = $value;
            }
        }
    }
    /**
     * Get singleton instance
     * @return Config
     */
    public static function getInstance($prod = false)
    {
        if (is_null(self::$instance)) {
            self::$instance = new Config($prod);
        }
        return self::$instance;
    }
}
