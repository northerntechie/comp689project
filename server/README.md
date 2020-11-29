# COMP689 Assignment 3 Project

A container based deployment of the OpenDSA project with a working subset of exercises for testing.

The project consists of:
- A Node.JS server that provides a simple client HTML page and a REST based service for providing a catalog and statically served exercises.
- A Moodle plugin client to incorporate into the Moodle education content management system.
- Docker build files (Dockerfile) for building containers of the various components.

# Docker server build

The server/ component can be built into a Docker container by running the following command in the server/ folder.

```$ docker build . -t <server_tag_name>```

The docker container can then be ran in interactive mode with the command,

```$ docker run -p <os_port>:8080 -d <server_tag_name>```

To interactively administer the docker container, the container can be interacted with using the bash interactive terminal with the command,

```$ ```