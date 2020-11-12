const fs = require('fs');
const { v5: uuidv5, v5 }  = require('uuid');

module.exports.build = (rootPath, callback) => {
    catalog = [];

    entry = fs.readdir(rootPath, (err, filenames) => {
        if (err) {
            callback(err,null);
        }
        else {
            filenames.forEach((entry) => {
                if (entry.isDirectory) {
                    ;
                }
                else if (entry.isFile) {
                    catalog.push({
                        "name" : entry.name(),
                        "GUID" : v5.GUID(entry.name)
                    });
                }        
            });
        }
    });
    callback(null,catalog);
};