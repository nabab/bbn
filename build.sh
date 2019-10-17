phpcs -p --report-file=./build/phpcs.log.xml ./src
phploc --log-xml=./build/phploc.xml ./src
phpmd ./src xml cleancode codesize > ./build/phpmd.xml
phpdox

