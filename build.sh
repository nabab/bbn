echo "------------------------------------- Starting PHPCS -----------------------------------------------"
./vendor/bin/phpcs -p --report-file=./build/phpcs.xml ./src
echo "------------------------------------- Starting PHPLoc -----------------------------------------------"
./vendor/bin/phploc --log-xml=./build/phploc.xml ./src
echo "------------------------------------- Starting PHPMD -----------------------------------------------"
./vendor/bin/phpmd ./src xml cleancode codesize > ./build/phpmd.xml
echo "------------------------------------- Starting PHPDOX -----------------------------------------------"
./vendor/bin/phpdox
echo "------------------------------------- END Build  -----------------------------------------------"

