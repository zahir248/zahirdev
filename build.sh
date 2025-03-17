#!/bin/bash

# Update package lists
apt update

# Install required dependencies
apt install -y python3 python3-pip ffmpeg

# Upgrade pip
pip3 install --upgrade pip

# Install yt-dlp
pip3 install --upgrade yt-dlp

# Ensure yt-dlp is executable
chmod +x $(which yt-dlp)
