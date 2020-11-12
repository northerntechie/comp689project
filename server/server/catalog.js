const fs = require('fs');
const { v5: uuidv5 }  = require('uuid');
const namespace = '123e4567-e89b-12d3-a456-426614174000';

var catalog = [];

module.exports.build = (rootPath, relPath, callback) => {
    entry = fs.readdir(rootPath, { withFileTypes : true }, (err, filenames) => {
        if (err) {
            callback(err,null);
        }
        else {
            filenames.forEach((entry) => {
                console.log(`Processing filenames entry: ${entry.name}, isFile= ${entry.isFile()}`);
                if (entry.isDirectory()) {
                    this.build(rootPath + '/' + entry.name, relPath + '/' + entry.name, (err, cat) => {
                        ;
                    });                  
                }
                else if (entry.isFile() && entry.name.endsWith('.html')) {
                    const name = entry.name.substr(0,entry.name.lastIndexOf('.html'));
                    catalog.push({
                        "name" : name,
                        "path" : relPath + '/' + entry.name,
                        "GUID" : uuidv5(entry.name, namespace)
                    });
                }        
            });
        }
    });
    callback(null,catalog);
};