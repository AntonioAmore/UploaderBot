<?php
namespace UploaderBot;


class Image implements \Serializable
{
    /** @var  string */
    public $url;
    /** @var  string */
    public $filename;
    /** @var  string */
    public $action;

    /**
     * @param $url
     * @param $action
     */
    function __construct($url = null, $action = null)
    {
        $this->url = $url;
        $this->filename = pathinfo($url, PATHINFO_FILENAME);
        $this->action = $action;
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        return json_encode($this);
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     */
    public function unserialize($serialized)
    {
        $temp = json_decode($serialized);

        if (isset($temp->url)) {
            $this->url = $temp->url;
        }

        if (isset($temp->action)) {
            $this->action = $temp->action;
        }

        if (isset($temp->filename)) {
            $this->filename = $temp->filename;
        }
    }
}