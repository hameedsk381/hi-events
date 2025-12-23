#!/bin/sh
# Wait for Node SSR server to be ready before starting Nginx

MAX_ATTEMPTS=30
ATTEMPT=0
NODE_PORT=${NODE_PORT:-5678}

echo "Waiting for Node SSR server to be ready on port $NODE_PORT..."

while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    if nc -z localhost $NODE_PORT 2>/dev/null; then
        # Check if health endpoint responds
        if wget -q -O- http://localhost:$NODE_PORT/health > /dev/null 2>&1; then
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

