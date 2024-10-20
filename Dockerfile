FROM docker.io/library/php:8.3-cli
WORKDIR /workdir
ENTRYPOINT [ "/bb" ]
ADD --chmod=755 bb.phar /bb
RUN <<-EOF
        apt-get update
        apt-get install -y git
        apt-get clean
        rm -rf /var/lib/apt/lists/*
    EOF
