#!/usr/bin/perl
# see: http://stackoverflow.com/a/41498363

# Usage: ./gitdiff.pl [add]
#    add : add modified files to git

use warnings;
use strict;

my ($auto_add) = @ARGV;
if(!defined $auto_add) {
    $auto_add = "";
}

my @mods = `git status --porcelain 2>/dev/null | grep '^ M ' | awk '{ print \$2 }'`;
chomp(@mods);
for my $mod (@mods) {
    my $diff = `git diff -b $mod 2>/dev/null`;
    if($diff) {
        print $mod."\n";
        if($auto_add eq "add") {
            `git add $mod 2>/dev/null`;
        }
    }
}