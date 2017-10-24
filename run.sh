#!/bin/sh

PHP_BINARY="php"

while getopts "p:" OPTION 2> /dev/null; do
	case ${OPTION} in
		p)
			PHP_BINARY="$OPTARG"
			;;
	esac
done

./ci/lint.sh -p "$PHP_BINARY"

if [ $? -ne 0 ]; then
	echo Lint scan failed!
	exit 1
fi

echo -e "\nversion\nms\nstop\n" | "$PHP_BINARY" -dphar.readonly=0 src/DevTools/ConsoleScript.php --make ./
