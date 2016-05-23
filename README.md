# UploaderBot
The app resizes given images and saves them to  remote cloud storage.

##Installation
- Install RabbitMQ ([reference](https://www.rabbitmq.com/download.html))
- copy ```./config/config.ini.dist``` to ```./config/config.ini```
- edit ```./config/config.ini``` with actual settings
- be sure ```tmpDir```, ```outputDir``` and config are writeable for a user, which is launching the app
- install composer ([reference](https://getcomposer.org/doc/00-intro.md#locally))
- run ```composer.phar install```
- create Dropbox [application](https://www.dropbox.com/developers/apps/)
- copy ```dropbox-client-secret.json.dist``` to ```dropbox-client-secret.json```
- make ./bot executable

##Usage
    command [arguments]

##Available commands
- **schedule**    Add filenames to resize queue
- **resize**      Resize next images from the queue
- **status**      Output current status in format %queue%:%number_of_images%
- **upload**      Upload next images to remote storage
- **retry**       Re-upload failed images from queue

#Docker
There is a docker image which allow deploy and test the project without actual environment setup
##Usage
Run command ```sudo docker build -t "uploader_bot" ./config``` to build the image

The following command allows to run the container ```sudo docker run  --name test_instance -i -t uploader_bot``` with name ```test_instance```

To run the application execute ```/var/www/UploaderBot/bot

##Known issues

There is nothing ideal in this World. I'm working on fixing issues listed below

- You have to run ```service rabbitmq-server start``` when launch the container.
