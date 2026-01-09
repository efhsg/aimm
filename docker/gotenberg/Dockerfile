FROM gotenberg/gotenberg:8
USER root
RUN apt-get update \
    && apt-get install -y curl \
    && rm -rf /var/lib/apt/lists/*
USER gotenberg
