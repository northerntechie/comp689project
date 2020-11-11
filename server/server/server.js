'use strict';
const fs = require('fs');
const express = require('express');

const PORT = 3000;
const HOST = 'localhost';

const app = express();

//! Main document request
app.get('/', (req,res) => {
    res.send('Hello World');
});

//! Catalog request
app.get('/catalog', (req,res) => {
    res.send('Catalog');
});

//! Lesson request by id
app.get('/lesson/:id', (req,res) => {
    res.send(`Lesson ${req.params.id} requested.`);
});

app.listen(PORT, HOST);
console.log(`Running OpenDSA server on host: ${HOST} and port: ${PORT}`);