#!/bin/sh

FILE="/PATH/TO/custatic-output-endfile"
PROC_FILE="/PATH/TO/custatic-output-procfile"

if [ -e $FILE ]; then
  rm -f $FILE
  rsync -a --chmod=Da+x --delete /PATH/TO/SOURCE_DIR export.example.com:/PATH/TO/DISTINATION_DIR/
  rm -f $PROC_FILE
fi
