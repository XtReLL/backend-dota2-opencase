const log4js = require('log4js');
let configured = false;

module.exports = function(name, level = 'debug') {
    if (!configured) {
        log4js.configure({
            appenders: {
                file: {type: 'file', filename: `logs/${name}.log`}, console: {type: 'console'}
            },
            categories: {default: {appenders: ['file', 'console'], level: level} }
        });
        configured = true;
    }

    return log4js.getLogger();
};