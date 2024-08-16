#!/bin/bash

# Define the name of the zip file
ZIP_FILE="wc-postnet-delivery.zip"

# Remove the existing zip file if it exists
[ -f "$ZIP_FILE" ] && rm "$ZIP_FILE"

# Zip the plugin folder, excluding hidden files and folders
zip -r "$ZIP_FILE" . -x "*/\.*" "__MACOSX/*" "*.DS_Store" "*.git/*" "publish.sh" "docker/*" ".gitignore" "*.zip" -x "*/.*/**"

echo "Plugin zipped successfully into $ZIP_FILE"
