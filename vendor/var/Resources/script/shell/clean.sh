#!/usr/bin/env sh

test $# == 2 || exit 1
find $1 -iname tests -type d | xargs rm -rf -
find $1 -regex ".*\(doc\|docs\|polyfill-php54\|vendor\|\.git\)" -type d | xargs rm -rf -
mkdir -p "$1/symfony/polyfill-php54/Resources/stubs" && touch "$1/symfony/polyfill-php54/bootstrap.php"
find $1 -regex ".*\(README\|LICENSE\|DS_Store\|build.*\|\.git.*\).*" -type f | xargs rm -rf -
find $1 \( -iname phpunit\* -o -iname \*.markdown -o -iname \*.md -o -iname \*.rst \) -type f | xargs rm -rf -
find $1 -name "composer.*" -not -path "$1/$2/*" -type f | xargs rm -rf -