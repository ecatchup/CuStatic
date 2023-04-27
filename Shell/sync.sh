#!/bin/sh

FILE="/PATH/TO/custatic-output-endfile"
if [ -e $FILE ]; then
  rsync -a --chmod=Da+x --delete /PATH/TO/SOURCE_DIR export.example.com:/PATH/TO/DISTINATION_DIR/
  rm -f $FILE
fi
