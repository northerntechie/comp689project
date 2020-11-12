'use strict';
const fs = require('fs');
const express = require('express');
const catalog = require('./catalog.js');

var fullCatalog;

catalog.build('./modules/OpenDSA/Exercises/', '.', (err,cat) => {
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
app.use(express.static('./modules/OpenDSA/lib'));
app.use(express.static('./modules/OpenDSA/Exercises'));

//! Main document request
app.get('/', (req,res) => {
    res.send('Hello World');
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
    res.status(404);
})

app.listen(PORT, HOST, () => {
    console.log(`Running OpenDSA server on host: ${HOST} and port: ${PORT}`);
});
