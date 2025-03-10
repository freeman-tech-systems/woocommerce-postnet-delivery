#!/bin/bash

# Get the current directory
CURRENT_DIR=$(pwd)

# Main plugin file (update this to match your plugin's main file)
MAIN_PLUGIN_FILE="wc-postnet-delivery.php"

# WordPress.org plugin repository URL (update this with your plugin's SVN URL)
SVN_URL="https://plugins.svn.wordpress.org/delivery-options-postnet-woocommerce"

# Config file paths
CONFIG_FILE="deploy-config.conf"
TEMP_EXCLUDE="/tmp/svn-exclude-$$.txt"

# Load credentials from config file if it exists
if [ -f "$CONFIG_FILE" ]; then
    source "$CONFIG_FILE"
    SVN_USER=$WP_USERNAME
    SVN_PASS=$WP_PASSWORD
fi

# Prompt for credentials if not found in config
if [ -z "$SVN_USER" ] || [ -z "$SVN_PASS" ]; then
    read -p "WordPress.org username: " SVN_USER
    read -s -p "WordPress.org password: " SVN_PASS
    echo ""
fi

# Store credentials temporarily
SVN_ARGS="--username $SVN_USER --password $SVN_PASS --non-interactive --no-auth-cache"

# Extract version from the main plugin file
VERSION=$(grep -i "^[ \t/*#]*version:[ \t]*" "$MAIN_PLUGIN_FILE" | awk -F: '{print $2}' | tr -d ' \t\r\n')
if [ -z "$VERSION" ]; then
    echo "Error: Could not find version number in $MAIN_PLUGIN_FILE"
    exit 1
fi

# Local SVN directory
DEPLOY_DIR="$CURRENT_DIR/deploy"

echo "Preparing to deploy version $VERSION"

# Check if deploy directory exists
if [ ! -d "$DEPLOY_DIR" ]; then
    echo "Deploy directory not found. Creating and checking out SVN repo..."
    mkdir "$DEPLOY_DIR"
    svn co $SVN_ARGS "$SVN_URL" "$DEPLOY_DIR"
    if [ $? -ne 0 ]; then
        echo "Error: SVN checkout failed"
        exit 1
    fi
else
    echo "Updating existing SVN repo..."
    cd "$DEPLOY_DIR"
    svn update $SVN_ARGS
    if [ $? -ne 0 ]; then
        echo "Error: SVN update failed"
        exit 1
    fi
fi

# Clean up trunk directory
echo "Cleaning trunk directory..."
cd "$DEPLOY_DIR/trunk"
rm -rf *

# Clean up assets directory
echo "Cleaning assets directory..."
cd "$DEPLOY_DIR/assets"
rm -rf *

# Copy current plugin files to trunk, excluding docker and .gitignore files
echo "Copying current plugin files to trunk..."
cd "$CURRENT_DIR"

# Create a temporary exclude list from .gitignore
echo "docker/" > "$TEMP_EXCLUDE"
echo ".git/" >> "$TEMP_EXCLUDE"
echo ".gitignore" >> "$TEMP_EXCLUDE"
echo "publish.sh" >> "$TEMP_EXCLUDE"
echo "deploy/" >> "$TEMP_EXCLUDE"
echo "assets/" >> "$TEMP_EXCLUDE"
echo "deploy.sh" >> "$TEMP_EXCLUDE"
echo "deploy-config.template.conf" >> "$TEMP_EXCLUDE"
if [ -f ".gitignore" ]; then
    # Add .gitignore contents, filtering out empty lines and comments
    grep -v '^#' ".gitignore" | grep -v '^$' >> "$TEMP_EXCLUDE"
fi

# Use rsync with the exclude file
rsync -rc --exclude-from="$TEMP_EXCLUDE" . "$DEPLOY_DIR/trunk/"

# Copy assets directory
echo "Copying assets directory..."
if [ -d "assets" ]; then
    rsync -rc assets/ "$DEPLOY_DIR/assets/"
else
    echo "Warning: No assets directory found in plugin"
fi

# Clean up temporary file
rm "$TEMP_EXCLUDE"

# Create new version tag if it doesn't exist
echo "Creating new version tag..."
cd "$DEPLOY_DIR"
if [ ! -d "tags/$VERSION" ]; then
    svn cp trunk "tags/$VERSION"
else
    echo "Warning: Tag $VERSION already exists"
fi

# Add all changes to SVN
echo "Adding files to SVN..."
cd "$DEPLOY_DIR"
svn add --force trunk/* --auto-props --parents --depth infinity -q
svn add --force tags/* --auto-props --parents --depth infinity -q

# Remove deleted files
svn status | grep '^\!' | sed 's/! *//' | xargs -I% svn rm %@

# Show status before commit
echo "Changes to be committed:"
svn status

# Confirm before committing
read -p "Do you want to commit these changes? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]
then
    # Commit changes
    echo "Committing version $VERSION..."
    svn ci $SVN_ARGS -m "Release version $VERSION"
    echo "Deployment complete!"
else
    echo "Deployment cancelled"
fi

# Clear credentials from memory
SVN_ARGS=""
SVN_USER=""
SVN_PASS="" 