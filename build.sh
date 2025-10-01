#!/bin/bash

# Stop and remove containers, networks, volumes
docker-compose down

# Rebuild containers without cache
docker-compose build --no-cache

# Start containers in detached mode
docker-compose up -d
