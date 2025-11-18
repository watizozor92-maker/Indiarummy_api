#!/usr/bin/sh
file=$1
curl -X POST "https://api.cloudflare.com/client/v4/zones/1d85d85432c4e68f957af26e6e79c87b/purge_cache" -H "X-Auth-Email: yonogamesofficial@gmail.com" -H "X-Auth-Key: 62c0a43bd9c06cfe851843e2f9a969a031bf0" -H "Content-Type: application/json" --data '{"files":["https://img.yonogames.com/storage/'$file'"]}'