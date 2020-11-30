FROM node:13

# Create app directory
WORKDIR /usr/src/app

COPY package*.json ./

RUN npm install

# Build directory structure
RUN mkdir -p modules/JSAV
COPY ./modules/JSAV modules/JSAV

RUN mkdir -p modules/OpenDSA
COPY modules/OpenDSA ./modules/OpenDSA

RUN mkdir -p modules/lib
COPY modules/OpenDSA/lib ./modules/lib

RUN mkdir client
COPY client ./client

RUN mkdir server
COPY server ./server

EXPOSE 8080

CMD ["node", "server/server.js"]