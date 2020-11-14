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

const PORT = 3000;
const HOST = 'localhost';

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
    doc = "";
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

//! Lesson request by id
app.get('/lesson/:id', (req,res) => {
    res.send(`Lesson ${req.params.id} requested.`);
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
