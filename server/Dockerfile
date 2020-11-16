FROM node:13

# Create app directory
WORKDIR /usr/src/app

COPY package*.json ./

RUN npm install

# Build directory structure
RUN mkdir -p modules/JSAV/build
COPY modules/JSAV/build/* ./modules/JSAV/build/

RUN mkdir -p modules/OpenDSA/AV
COPY modules/OpenDSA/* ./
COPY modules/OpenDSA/AV/* ./

RUN mkdir -p modules/lib
COPY modules/lib/* ./

RUN mkdir server
COPY server/* ./server

EXPOSE 8080

CMD [ "node", "server/server.js" ]

