<?php
namespace UploaderBot;

use DirectoryIterator;
use Dropbox\AppInfo;
use Dropbox\Client;
use Dropbox\WebAuthNoRedirect;
use Dropbox\WriteMode;
use SplFileInfo;


class UploaderBot
{
    /** @var  Configuration */
    private $configuration;
    /** @var Queue [] */
    private $queues = [];

    function __construct($file)
    {
        $this->configuration = new Configuration($file);

        foreach (['resize', 'upload', 'failed', 'done'] as $name) {
            $this->queues[$name] = new Queue($this->configuration->get('rabbitMQ'), $name);
        }
    }

    public function run($argc, $argv)
    {

        if ($argc == 1) {
            $this->displayHelp();
        } else {
            if ($argc > 2) {
                $argument = $argv[2];
            } else {
                $argument = null;
            }

            try {
                switch ($argv[1]) {
                    case 'schedule':
                        $this->schedule($argument);
                        break;
                    case 'resize':
                        $this->resize($argument);
                        break;
                    case 'status':
                        $this->status();
                        break;
                    case 'upload':
                        $this->upload($argument);
                        break;
                    case 'retry':
                        $this->retry($argument);
                        break;
                    default:
                        $this->displayHelp();
                }
            } catch (\Exception $ex) {
                echo 'Error: ' . $ex->getMessage() . PHP_EOL;
                $this->displayHelp();
            }
        }
    }

    private function displayHelp()
    {
        echo <<<EOD
Uploader Bot
Usage:
    command [arguments]
Available commands:
    schedule    Add filenames to resize queue
    resize      Resize next images from the queue
    status      Output current status in format %queue%:%number_of_images%
    upload      Upload next images to remote storage
    retry       Re-upload failed images from queue

EOD;
    }

    /**
     * @param $argument
     * @throws \Exception
     */
    private function schedule($argument)
    {
        if (empty($argument)) {
            throw new \Exception("No path to directory with images provided");
        }

        if (!file_exists($argument) || !is_dir($argument)) {
            throw new \Exception("The directory does not exist");
        }

        /*
         * As there may be VERY much files, using this code instead of single scandir call
         */
        $dir = new DirectoryIterator($argument);

        /** @var SplFileInfo $fileInfo */
        foreach ($dir as $fileInfo) {
            if (!$fileInfo->isDot()) {
                $this->queueForResize(new Image($fileInfo->getRealPath()));
            }
        }
    }

    /**
     * @param $argument
     */
    private function resize($argument)
    {
        $limit = (int)$argument;

        $callback = function ($message) {
            $image = new Image();
            $image->unserialize($message);

            try {
                $file = $this->resizeImage($image);
                $this->queueForUpload(new Image($file));
                unlink($image->url);
            } catch (\Exception $ex) {
                echo $ex->getMessage() . PHP_EOL;
                $this->queueAsFailed($image, 'resize');
            }
        };

        $this->queues['resize']->dequeue($callback, $limit);
    }

    /**
     *
     */
    private function status()
    {
        echo 'Images Processor Bot' . PHP_EOL;
        echo "Queue\t\tCount" . PHP_EOL;
        foreach ($this->queues as $name => $queue) {
            echo "{$name}\t\t{$queue->size()}" . PHP_EOL;
        }
    }

    /**
     * @param $argument
     */
    private function upload($argument)
    {
        $limit = (int)$argument;

        $callback = function ($message) {
            $image = new Image();
            $image->unserialize($message);

            try {
                $file = $this->uploadImage($image);
                $this->queueAsDone(new Image($file));
            } catch (\Exception $ex) {
                echo $ex->getMessage() . PHP_EOL;
                $this->queueAsFailed($image, 'upload');
            }
        };

        $this->queues['upload']->dequeue($callback, $limit);
    }

    /**
     * @param $argument
     */
    private function retry($argument)
    {
        $limit = (int)$argument;
        $callback = function ($message) {
            $image = new Image();
            $image->unserialize($message);

            $this->queues[$image->action]->enqueue($message);
        };

        $this->queues['failed']->dequeue($callback, $limit);
    }

    /**
     * @param Image $image
     * @return string
     * @throws \Exception
     */
    private function resizeImage(Image $image)
    {
        $error = false;
        $file = $image->url;

        if (!file_exists($file)) {
            throw new \Exception("Cannot open file {$file}");
        }

        @list($width, $height, $type) = getimagesize($file);

        switch ($type) {
            case IMAGETYPE_BMP:
                $oldImage = imagecreatefromwbmp($file);
                break;
            case IMAGETYPE_GIF:
                $oldImage = imagecreatefromgif($file);
                break;
            case IMAGETYPE_JPEG:
                $oldImage = imagecreatefromjpeg($file);
                break;
            case IMAGETYPE_PNG:
                $oldImage = imagecreatefrompng($file);
                break;
            default:
                throw new \Exception("Unknown file type: {$file}");
        }

        if ($oldImage === false) {
            $error = $error || true;
        }

        $newWidth = $this->configuration->get('image')['width'];
        $newHeight = $this->configuration->get('image')['height'];

        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        $color = imagecolorallocate($newImage, 255, 255, 255);
        $error = $error || !imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $color);

        if (($width / $height) >= ($newWidth / $newHeight)) {
            // by width
            $nw = $newWidth;
            $nh = $height * ($newWidth / $width);
            $nx = 0;
            $ny = round(abs($newHeight - $nh) / 2);
        } else {
            // by height
            $nw = $width * ($newHeight / $height);
            $nh = $newHeight;
            $nx = round(abs($newWidth - $nw) / 2);
            $ny = 0;
        }

        $error = $error || !imagecopyresized($newImage, $oldImage, $nx, $ny, 0, 0, $nw, $nh, $width, $height);

        $extension = $this->configuration->get('image')['extension'];
        $outputDir = $this->configuration->get('outputDir');

        switch ($extension) {
            case 'jpg':
                $function = 'imagejpeg';
                break;
            case 'gif':
                $function = 'imagegif';
                break;
            case 'bmp':
                $function = 'imagewbmp';
                break;
            case 'png':
                $function = 'imagepng';
                break;
            default:
                $extension = 'jpg';
        }

        if (!file_exists($outputDir)) {
            if (!mkdir($outputDir)) {
                throw new \Exception("Cannot create output dir");
            }
        }

        $resultFileName = $outputDir . DIRECTORY_SEPARATOR . $image->filename . '.' . $extension;
        $error = $error || !$function($newImage, $resultFileName);

        imagedestroy($newImage);
        imagedestroy($oldImage);

        if ($error) {
            throw new \Exception("Resize error for {$image->url}");
        }

        return $resultFileName;
    }

    /**
     * @param Image $image
     * @return string
     * @throws \Exception
     */
    private function uploadImage(Image $image)
    {

        @$accessToken = file_get_contents(
            $this->configuration->getConfigPath() . DIRECTORY_SEPARATOR . 'dropbox-client-token'
        );
        if (empty($accessToken)) {
            $appInfo = AppInfo::loadFromJsonFile(
                $this->configuration->getConfigPath() . DIRECTORY_SEPARATOR . "dropbox-client-secret.json"
            );
            $webAuth = new WebAuthNoRedirect($appInfo, $this->configuration->get('dropbox')['appName']);
            $authorizeUrl = $webAuth->start();
            echo "1. Go to: " . $authorizeUrl . "\n";
            echo "2. Click \"Allow\" (you might have to log in first).\n";
            echo "3. Copy the authorization code.\n";
            $authCode = \trim(\readline("Enter the authorization code here: "));
            list($accessToken, $dropboxUserId) = $webAuth->finish($authCode);

            if (empty($accessToken)) {
                throw new \Exception('Cannot get dropbox token');
            }

            file_put_contents(
                $this->configuration->getConfigPath() . DIRECTORY_SEPARATOR . 'dropbox-client-token',
                $accessToken
            );
        }

        $dbxClient = new Client($accessToken, $this->configuration->get('dropbox')['appName']);

        @$f = fopen($image->url, "rb");
        if (empty($f)) {
            throw new \Exception("Cannot open file {$image->url}");
        }

        $result = $dbxClient->uploadFile(DIRECTORY_SEPARATOR . basename($image->url), WriteMode::force(), $f);
        fclose($f);

        if (!isset($result['size'])) {
            throw new \Exception("Cannot upload file {$image->url}");
        }

        return $image->url;
    }

    /**
     * @param Image $image
     * @param $action
     */
    private function queueAsFailed(Image $image, $action)
    {
        $image->action = $action;
        $this->queues['failed']->enqueue($image->serialize());
    }

    /**
     * @param Image $image
     */
    private function queueForUpload(Image $image)
    {
        $this->enqueue($image, 'upload');
    }

    /**
     * @param Image $image
     */
    private function queueAsDone(Image $image)
    {
        $this->enqueue($image, 'done');
    }

    /**
     * @param Image $image
     */
    private function queueForResize(Image $image)
    {
        $this->enqueue($image, 'resize');
    }

    private function enqueue(Image $image, $action)
    {
        $image->action = $action;
        $this->queues[$action]->enqueue($image->serialize());
    }
}