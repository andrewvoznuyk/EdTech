# This Makefile is designed to be included by another Makefile located in your project directory.
# ==> https://github.com/EmakinaFR/docker-magento2/wiki/Makefile

SHELL := /bin/sh
DEBUG = docker-compose exec fpm_xdebug bash

##
## ----------------------------------------------------------------------------
##   Environment
## ----------------------------------------------------------------------------
##

start:## Start containers
	docker-compose up -d

stop: ## Stop containers
	docker-compose stop

down: ## Down containers
	docker-compose down

db: ## Open a terminal in the "mysql" container
	docker-compose exec db sh

ps: ## List all containers managed by the environment
	docker-compose ps

mt:
	mutagen project terminate

ms:
	mutagen project start

mr: ## Restart mutagen project
	mt ms

restart: ## Restart containers
	stop start 
