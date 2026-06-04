# Dockerfile untuk Node.js
FROM node:20-alpine

# Install dependencies
RUN apk add --no-cache \
    git \
    python3 \
    make \
    g++

# Set working directory
WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm install

# Expose port
EXPOSE 3000 5000 5173

# Default command
CMD ["npm", "run", "dev"]
