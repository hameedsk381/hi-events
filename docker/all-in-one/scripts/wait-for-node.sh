#!/bin/sh
# Wait for Node SSR server to be ready before starting Nginx

MAX_ATTEMPTS=30
ATTEMPT=0
NODE_PORT=${NODE_PORT:-5678}

echo "Waiting for Node SSR server to be ready on port $NODE_PORT..."

while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    # Check if port is listening
    if nc -z localhost $NODE_PORT 2>/dev/null; then
        # Check if health endpoint responds with proper status code
        if wget --spider --quiet --tries=1 --timeout=2 http://localhost:$NODE_PORT/health 2>/dev/null; then
            echo "Node SSR server is ready!"
            exit 0
        fi
    fi
    
    ATTEMPT=$((ATTEMPT + 1))
    echo "Attempt $ATTEMPT/$MAX_ATTEMPTS: Node SSR not ready yet, waiting..."
    sleep 2
done

echo "WARNING: Node SSR server did not become ready after $MAX_ATTEMPTS attempts"
echo "Starting Nginx anyway - it will retry connections"
exit 0

