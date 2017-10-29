Magento availability, price, cost mass updater via CSV-file.

Installation:

1. Copy app and Lycenok-Sripts to magento root folder

2. If you have a PHP cache (for example, apc), restart web server 
(e.g. apachectl graceful)

3. Clear magento cache ("System" -> "Cache management" -> "Flush Magento Cache")

4. Go to <your url>/Lycenok-Sripts/update-product.php

Enjoy! 

(worked and tested on magento 1.9.3)

Example of CSV (just leave columns empty, if you don't want to update, the representation on github project page is not perfect,
see example.csv):


SKU,Availability,Sell Price,Cost
GV-N1050OC-2GL,In Stock,,0
K420-2GB,In Stock at Suppliers (1-3 Business Days),169,139
NVS-315,In Stock at Suppliers (1-3 Business Days),159,145
QUADRO K620,In Stock at Suppliers (1-3 Business Days),233,202
P1000,Back Order - Please Call for ETA,453,409
K1200 mDP-DVI,Back Order - Please Call for ETA,465,425
NVS-510,Back Order - Please Call for ETA,518,479
M4000,Back Order - Please Call for ETA,1188,999
P5000,Back Order - Please Call for ETA,2962,"2,499.00"
P6000,Back Order - Please Call for ETA,7132,"6,499.00"

