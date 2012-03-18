require('mootools').apply(GLOBAL);

var cli = require('cli'),     
    yaml = require('js-yaml'), 
    fs = require('fs');

var Config = new Class({
    Implements: [Options],
    options:{
        path: ['/var/www/config/config.neon', '/var/www/config/config.local.neon'],
        args: process.argv
    },
    initialize:function(options){
        this.setOptions(options);
    },
    parse:function(){
        var opts = {};
        for(var i = 0; i < this.options.path.length; i++){
            try{
                //cli.debug('Reading config file: ' + this.options.path[i]);
                opts = Object.merge(opts, yaml.load(
                    fs.readFileSync(this.options.path[i], 'utf8')
                ).production.parameters || {});
            }
            catch(e){
                //cli.debug(e, 'skipping...');
            }
        }
        opts = Object.merge(opts, cli.parse(/*{
            jobs:           [null, 'Number of threads to run in', 0],
            port:           [null, 'Port for Socket.IO to listen at', opts.port],
            logdir:         [null, 'Where to log errors', opts.logdir],
            staticServer:   [null, 'Path to static files', opts.staticServer],
            userServer:     [null, 'Path to user content', opts.userServer],
            usock:          [null, 'Path to unix socket for PHP', opts.usock]
        }*/));
        return opts;
    }
});

exports.parse = function(options){
    return new Config(options).parse();
}