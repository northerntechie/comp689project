'use strict';
const fs = require('fs');
const express = require('express');
const catalog = require('./catalog.js');

var fullCatalog;

catalog.build('./modules/OpenDSA/AV/', '/AV', (err,cat) => {
    if(err) {
        fullCatalog = {};
    }
    else {
        fullCatalog = cat;
    }
});

const PORT = 8080;
const HOST = '0.0.0.0';

const app = express();
app.use('/lib/',express.static('./modules/OpenDSA/lib'));
app.use('/Exercises/JSAV',express.static('./modules/JSAV'));
app.use('/Exercises/',express.static('./modules/OpenDSA/Exercises'));
app.use('/AV',express.static('./modules/OpenDSA/AV'));
app.use('/DataStructures',express.static('./modules/OpenDSA/DataStructures'));
app.use('/ODSAkhan-exercises/',express.static('./modules/OpenDSA/khan-exercises'));
app.use('/khan-exercises/',express.static('./modules/OpenDSA/khan-exercises'));
app.use(express.static('./client'));

//! Main document request
app.get('/', (req,res) => {
    fs.readFile("file://client/index.html", (err, data) => {
        if(err) {
            res.sendStatus(404);
        }
        else {
            res.send(data.toString());
        }
    });
});

//! Catalog request
app.get('/catalog', (req,res) => {
    res.send(fullCatalog);
});

//! OpenDSA exercise request by id
app.get('/exercise/:id', (req,res) => {
    console.log(`Processing request for opendsa-exercise: {req.params.id}`)
    res.send(`Exercise ${req.params.id} requested.`);
});

//! Default handler for all other requests
app.use(function(req, res){
    console.log(`Received unhandled request: ${req.originalUrl}`);
    res.status(404);
    res.send();
})

app.listen(PORT, HOST, () => {
    console.log(`Running OpenDSA server on host: ${HOST} and port: ${PORT}`);
});
