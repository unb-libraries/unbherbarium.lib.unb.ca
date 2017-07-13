#!/bin/bash

awk -F',' -v OFS=',' '
  NR == 1 {print $0, "ID"; next}
  {print $0, (NR-1)}
' herbarium_samples.csv

