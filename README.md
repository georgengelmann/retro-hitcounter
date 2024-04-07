# retro-hitcounter

This retro hit counter tracks the number of unique visitors to a website and displays the count using images for each digit. The counter differentiates between new and returning visitors based on IP address and a configurable time interval.

The counter is live on https://fulcrumserver.org

## Installation

- Copy the files to your webspace
- Run database.sql on a MariaDB server and change the config.php variables to your database
- include('hitcounter.php') somewhere on your website

Hitcounter images from https://github.com/mholt/caddy-hitcounter