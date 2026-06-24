<?php

use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Connection;

##########################################################################
##########################################################################
# App common
##########################################################################
##########################################################################
$entries['app.connection'] = function () {
    global $wpdb;

    return new Connection($wpdb);
};


################
# Repositories #
################
require 'repositories.php';

############################
# Currently logged in user #
############################
require 'infrastructure.user.php';

###################
# Domain Services #
###################
require 'domain.services.php';

########################
# Application Services #
########################
require 'application.services.php';

########################
# Infrastructure Services #
########################
require 'infrastructure.services.php';

###############
# Command bus #
###############
require 'command.bus.php';

####################
# Domain event bus #
####################
require 'domain.event.bus.php';

######################
# Request overriding #
######################
require 'request.php';

return new Container($entries);
