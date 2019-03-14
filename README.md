# Introduction
The main purpose of Magento Devtool is to organize and simplify development of Magento 1 and Magento 2 projects.
The Devtool was born in ISM eCompany and is actively used by all PHP teams since 2012.

>BE AWARE!
>Documentation is incomplete yet.
>Any references to ISM eCompany were removed from the sources thus not everything will go perfect to you.

# Main features
* Magento deployments
* Create development copy of a project including auto database configuration
* Advanced database import to development environment from any other environment
* Simple PHP, Bash, SQL consoles to all the project environments
* Auto login to Backoffice to any environment the Devtool has SSH access
* Handy information and navigation concerning the projects
* Git accessories
* Centralized setup for non-developer features (implies authorization and ACL configuration)
* Etc.

# How it works
The Devtool is written on PHP and use Bash and SSH API to do all required stuff. On development environment it works on behalf of developer user name. Centralized setup use own "devtool" user which needs individual access grant.
The Magento Devtool is compatible with the following Git branching strategy
* All testing environments must contain Alpha or Beta keyword
* Testing environment must be under Git and must be on corresponding branch, for example Alpha or Beta
* Production also must be under Git and some tag name. If project requires compilation, Devtool will create intermediate "Live" branch and commit compilation results there

# Installation
**Requirements:**
* Ubuntu (recommended 18.04)
* Packages: net-tools, nginx, php-fpm, php, composer
* Sudo without password, if by some security requirements you can't do it, you need to setup the Devtool in Docker

To setup the Devtool you need to execute following command in project folder:
```bash
sudo php install/nginx.php
```

# History
The first version was created by Team Leader Alexander Veselovsky for personal use in order to be able to perform bunch of deployments quickly. The Devtool went viral and in a short time it became obligatory for all members as it reduces mistakes and makes complex teaching of new members much easier. All that years the Devtool is a full-fledged internal project inside ISM eCompany and it got a lot of features and improvements.
