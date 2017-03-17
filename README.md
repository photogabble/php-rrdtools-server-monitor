# Basic Server Monitoring using PHP and RRDTools

If you feel that a monitoring service such as Cacti is too heavy for you needs you may find this project of interest.
This provides monitoring with minute resolution for Nginx, CPU Usage, Network Traffic and Memory Usage.

In the future I will be adding IO and MySQL monitoring.

## Requirements

* [php-rrd](http://php.net/manual/en/book.rrd.php) extension be installed - install on Ubuntu via `apt-get install php-rrd`
* [sysstat](http://sebastien.godard.pagesperso-orange.fr/) package be installed. This includes the mpstat command used for CPU reporting - install on Ubuntu via `apt-get install sysstat`
