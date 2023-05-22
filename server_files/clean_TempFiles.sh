#!/bin/bash

# Use the -exec command insead of the -delete.  The reason is that for directories with large amounts of files it can exceed the "rm" argument list limit.
# Using -exec will fork a new process for each file it is deleting.


# Wait 8 days before deleting the JPEG background because they save the most for the processer, and have Cropped and Flattened multiple images into one, and does not change, even if the text does on top.
# PDF Singles can still make use of the Cached JPEG backgrounds, but change any time text in the editing tool changes, etc.
# The Image IDs will change anytime someone resizes a graphic, etc... that should be fairly rare (exept for picky people)... and this is our lowest level caching trick.
find /home/printsma/ImageCaching/JPEG_Backgrounds/* -mtime +5 -exec rm -f {} \;
find /home/printsma/ImageCaching/PDFsingles/* -mtime +1 -exec rm -f {} \;
find /home/printsma/ImageCaching/ImageIDs/* -mtime +3 -exec rm -f {} \;


find /home/printsma/TempFiles/* -mtime +1 -exec rm -f {} \;
