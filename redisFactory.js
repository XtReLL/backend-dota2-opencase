const redis = require('redis');
const retryStrategy = require('node-redis-retry-strategy');

module.exports = {
    buildDefault: function (log, tag) {
        const client = redis.createClient({
            retry_strategy: retryStrategy({
                number_of_retry_attempts: -1,
                delay_of_retry_attempts: 3000,
                wait_time: 600000
            })
        });

        client.on('error', function (err) {
            log.warn(`Redis error: ${err}`);
        });

        client.on('reconnecting', function (e) {
            log.info(`Redis reconnecting (attempt # ${e.attempt}): ${e.error.code}`);
        });

        return client;
    }
};