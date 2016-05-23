<?php
namespace UploaderBot;


class Configuration
{
    /** @var  string */
    private $configPath;

    /**
     * @param string $file
     * @throws \Exception
     */
    function __construct($file)
    {
        if (empty($file) || !file_exists($file)) {
            throw new \Exception("Config does not exist");
        }

        $config = parse_ini_file($file, true);
        if (!$config) {
            throw new \Exception('Invalid config file');
        }

        foreach ($config as $key => $value) {
            $this->set($key, $value);
        }

        $this->configPath = pathinfo($file, PATHINFO_DIRNAME);
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value)
    {
        $this->$key = $value;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function get($key)
    {
        if (isset($this->$key)) {
            return $this->$key;
        } else {
            return null;
        }
    }

    /**
     * @return string
     */
    public function getConfigPath()
    {
        return $this->configPath;
    }
} 