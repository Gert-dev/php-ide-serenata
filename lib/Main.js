'use strict';

const ServiceContainer = require('./ServiceContainer');

const container = new ServiceContainer();

module.exports = container.getSerenataClient();
