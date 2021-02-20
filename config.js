const config = {
  domainServer: 'https://candy-cases.com',
  nameSite: 'candy-cases.com',
  bot: {
    username: '',
    password: '',
    shared_secret: '',
    identity_secret: ''
  },
  cert: {
  	public: '/etc/letsencrypt/live/candy-cases.com/fullchain.pem',
  	private: '/etc/letsencrypt/live/candy-cases.com/privkey.pem'
  }
};

module.exports = config;
