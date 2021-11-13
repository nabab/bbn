#!/bin/bash
echo "------------------------------------- Starting PHPUnit tests -----------------------------------------------"
./vendor/bin/phpunit tests/* --log-junit=junit_report.xml --coverage-xml=coverage_php.xml
echo "------------------------------------- Endof PHPUnit tests -----------------------------------------------"
