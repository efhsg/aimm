FROM python:3.11-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
    gcc \
    libfreetype6-dev \
    libpng-dev \
    libjpeg-dev \
    zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

ARG USER_ID=1000
ARG USER_NAME=appuser

RUN adduser --uid ${USER_ID} --disabled-password --gecos "" ${USER_NAME}

WORKDIR /app

COPY python-renderer/requirements.txt /app/requirements.txt
RUN python -m pip install --no-cache-dir -r requirements.txt

RUN chown -R ${USER_NAME}:${USER_NAME} /app

USER ${USER_NAME}

CMD ["tail", "-f", "/dev/null"]

