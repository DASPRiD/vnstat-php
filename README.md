# vnStat-PHP

This is a simple PHP frontend for vnStat to nicely display statistics about your
network traffic. All you have to do is to install vnStat on your system.
Preferably you run it as daemon, but you can theoretically also run it in user
mode, please refer to the vnStat documentation. After that, just direct a
virtual host to this directory and you are done.

## Configuring multiple or different interfaces

You can easily change the used interface or add more by copying the
"config.php.dist" file to "config.php" and adjust the values in it.

## Example output

![](https://github.com/dasprid/vnstat-php/blob/master/example.png)