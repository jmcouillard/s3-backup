# S3 backups

Setup an automated backup of your webserver to an Amazon S3 account. Optimized for Mediatemple DV Managed 4, in "power-user" mode.

Should be working correctly on any CentOS/Plesk 11.5 system.

Adapted from [github.com/mdylanbell/gsbackup](https://github.com/mdylanbell/gsbackup), without the web application part.

## Overview

Take all the files and databases of your webserver and upload it to your S3 account.

It consists of a two-steps script:

1. *s3backups.php* looks for the websites to be backed-up and generate a custom shell-script.
2. *backup.sh* write-out the script generated by *s3backups.php* and execute it.

The script is currently optimzed for Mediatemple DV Managed 4 server.

## Usage

Once installed, connect to your webserver through SSH, go to the location where you've installed the script and launch the script using:

`sh backup.sh`

You could also trigger the shell-script as a cron-job, but I prefer using as an [Alfred](http://www.alfredapp.com/) task.

## Required file structure

The current version of the script is deigned to be used with Mediatemple DV Managed 4 server, so the folder structure needs to be the following:

- DOMAIN_PATH
	- webspace1
		- HTTP_FOLDER
			- mywebsite1.com
			- mywebsite2.com
			- ...
	- webspace2
		- HTTP_FOLDER
			- mywebsite4.com
			- mywebsite5.com
			- ...
	- webspace3
		- HTTP_FOLDER
			- mywebsite7.com
			- mywebsite8.com
			- ...
			
If your folder is not the same, you will need to edit the script.


## Installation

### 1. S3 command line tool

Follow th instructions on this page (http://s3tools.org/repositories). For a Mediatemple DV Managed 4, the procedure would be the following :

1. Connect to your server as root.
2. Go to /etc/yum.repos.d
3. Type `wget http://s3tools.org/repo/RHEL_6/s3tools.repo` to download the package.
4. Type `yum install s3cmd` to install the package.
5. You now have s3cmd installed.

### 2. Configure S3

You will need to create an Access Key and an Secret Access Key to access your S3 account. Use the Amazon Account Center to do so. Then, configure you S3 connection with the keys you received by email, using the following command :

`s3cmd --configure`

You can make sure the connection is good by listing your current buckets:

`s3cmd ls`

or if you have some permission issues, you might have to select a specific bucket. For example:

`s3cmd ls s3://jmcouillard/gsbackup/`


### 3. Upload files

Upload the files of this repository to your webserver. Use a non-web-accessible location. Usually, there is a private folder on your server for that.


### 4. Settings for s3backups.php

You will need to edit s3backups.php in order to reflect your server specifications.


#### Paths settings

- **DOMAINS_PATH:** The location of your webspaces. 
- **HTTP_FOLDER:** The location of your websites, relative to the webspace.
- **BACKUP_PATH:** This is wehere the backup files (tar.gz) files will be created.

#### Databse settings

- **PYTHONPATH:** The path of *python library*. To find where it is use this command in the terminal : `which python`.
- **S3CFG_FILE_PATH:** The abosulte path to .s3cfg file. Usually, it is `~/.s3cfg`.
- **S3CMD_FILE:** The path of *python s3cmd*. To find where it is use this command in the terminal : `which s3cmd`.
- **S3_REMOTE_PATH:** the path of the bucket you files will be uploaded to.

#### Databse settings

- **DB_HOST:** Database hostname (most of the time, this is localhost)
- **DB_USER:** Database master username.
- **DB_PASS:** Database master user password. Optional if you use DB_PASS_SHADOW.
- **DB_PASS_SHADOW:** On some server, the MySQL master user password is stored in a file. If you have th elocation of this file, you can use a ``cat /etc/psa/.psa.shadow`` style value. If you don't know what I'm talking about, comment this line and only use DB_PASS.


### 5. Settings for backup.sh

As shell-shortcut, *backup.sh* is a two-lines file that consist of the follwing :

```
php s3backups.php webspace1.com webspace2.com > ./backups/script.sh
sh ./backups/script.sh

```

You simply need to replace `webspace1.com` and `webspace1.com` (and add as many as you want) by the name of your webspaces. These are passed as arguemnts to the php script.


### Optional : Register SSH key (avoid password)

To avoid having to enter the password at each time, you can register your public ssh key on your server, as described in the followong article :

https://kb.mediatemple.net/questions/1626/Using+SSH+keys+on+your+server