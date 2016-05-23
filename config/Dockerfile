############################################################
# Dockerfile to build UploaderBot container images
# Based on Ubuntu
############################################################

# Set the base image to Ubuntu
FROM ubuntu:14.04

# File Author / Maintainer
MAINTAINER AntonioAmore

ENV DEBIAN_FRONTEND noninteractive

# Update the repository sources list
RUN apt-get update

# Avoid ERROR: invoke-rc.d: policy-rc.d denied execution of start.
RUN echo "#!/bin/sh\nexit 0" > /usr/sbin/policy-rc.d
RUN apt-get upgrade -y

RUN locale-gen en_US en_US.UTF-8
ENV LANG en_US.UTF-8
ENV LANGUAGE en_US.UTF-8

################## BEGIN INSTALLATION ######################
RUN apt-get install -y rabbitmq-server
RUN rabbitmq-plugins enable rabbitmq_management && \
  echo "[{rabbit, [{loopback_users, []}]}]." > /etc/rabbitmq/rabbitmq.config

RUN apt-get install -y git
RUN apt-get install -y curl

#install and configure PHP5
RUN apt-get install -y php5-cli php5-common php5-curl php5-gd
RUN sudo php5enmod pdo
RUN sudo php5enmod readline
RUN sudo php5enmod json


#configure the app
RUN mkdir /var/www
RUN mkdir /var/www/UploaderBot

RUN git clone https://github.com/AntonioAmore/UploaderBot /var/www/UploaderBot
RUN chmod +x /var/www/UploaderBot/bot

RUN cp /var/www/UploaderBot/config/config.ini.dist /var/www/UploaderBot/config/config.ini
RUN cp /var/www/UploaderBot/config/dropbox-client-secret.json.dist /var/www/UploaderBot/config/dropbox-client-secret.json
RUN curl https://getcomposer.org/installer | php -- && mv composer.phar /usr/local/bin/composer && chmod +x /usr/local/bin/composer
RUN composer install -d /var/www/UploaderBot

RUN mkdir /var/www/UploaderBot/images
RUN mkdir /var/www/UploaderBot/images_resized

##################### INSTALLATION END #####################
RUN echo -e "\nexport TERM=xterm" >> ~/.bashrc
RUN update-rc.d rabbitmq-server enable
RUN echo "\n***************************************************************\n\
You have create and configure Dropbox API application manually!\
\n***************************************************************\n"

ENTRYPOINT ["/bin/bash"]