#!/usr/bin/env sh

test $# == 2 || exit 1
find $1 -iname tests -type d | xargs rm -rf -
find $1 -regex ".*\(doc\|docs\|password-compat\|polyfill-mbstring\|polyfill-php54\|polyfill-php55\|vendor\|\.git\)" -type d | xargs rm -rf -
mkdir -p "$1/ircmaxell/password-compat/lib" && touch "$1/ircmaxell/password-compat/lib/password.php"
mkdir -p "$1/symfony/polyfill-mbstring" && touch "$1/symfony/polyfill-mbstring/bootstrap.php"
mkdir -p "$1/symfony/polyfill-php54/Resources/stubs" && touch "$1/symfony/polyfill-php54/bootstrap.php"
mkdir -p "$1/symfony/polyfill-php55" && touch "$1/symfony/polyfill-php55/bootstrap.php"
find $1 -regex ".*\(README\|LICENSE\|DS_Store\|build.*\|\.git.*\).*" -type f | xargs rm -rf -
find $1 \( -iname phpunit\* -o -iname \*.markdown -o -iname \*.md -o -iname \*.rst \) -type f | xargs rm -rf -
find $1 -name "composer.*" -not -path "$1/$2/*" -type f | xargs rm -rf -