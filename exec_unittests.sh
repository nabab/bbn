#!/bin/bash
echo "------------------------------------- Starting PHPUnit tests -----------------------------------------------"
./vendor/bin/phpunit tests/* --log-junit=junit_report.xml --coverage-xml=coverage_php.xml
sleep 2
echo "------------------------------------- Endof PHPUnit tests -----------------------------------------------"
