/*
  Jenkins: Pipeline from SCM → https://github.com/whogauravyadav/ca-app-web.git (main)
  Prerequisites: Docker CLI on agent, MySQL container `ca-mysql` on `risebix-net`,
  host .env at HOST_ENV_FILE.
*/
pipeline {
  agent any

  options {
    timestamps()
    disableConcurrentBuilds()
    skipDefaultCheckout(true)
  }

  environment {
    IMAGE_NAME       = 'ca-app-web'
    CONTAINER_NAME   = 'ca-app-web'
    APP_PORT         = '8701'
    DOCKER_NETWORK   = 'risebix-net'
    DB_OVERRIDE_HOST = 'ca-mysql'
    DB_OVERRIDE_PORT = '3306'
    HOST_ENV_FILE    = '/home/grv/current-affairs-app/backend/.env'
    HOST_APP_ROOT    = '/home/grv/current-affairs-app/backend'
  }

  stages {
    stage('Repair workspace ownership') {
      steps {
        sh '''
          set -eu
          docker run --rm \
            -e U="$(id -u)" \
            -e G="$(id -g)" \
            -v "${WORKSPACE}:/ws:rw" \
            alpine:3.20 \
            sh -c 'for d in /ws/storage /ws/bootstrap/cache; do [ -e "$d" ] && chown -R "$U:$G" "$d"; done; exit 0'
        '''
      }
    }

    stage('Checkout') {
      steps {
        checkout scm
      }
    }

    stage('Ensure MySQL network') {
      steps {
        sh '''
          set -eux
          docker network create ${DOCKER_NETWORK} || true
          # Attach ca-mysql to app network if it exists and is not already connected
          if docker ps -a --format '{{.Names}}' | grep -qx ca-mysql; then
            docker network connect ${DOCKER_NETWORK} ca-mysql 2>/dev/null || true
          fi
        '''
      }
    }

    stage('Build Docker Image') {
      steps {
        sh '''
          set -eux
          docker build --pull -t ${IMAGE_NAME}:latest "${WORKSPACE}"
        '''
      }
    }

    stage('Stop Previous Container') {
      steps {
        sh '''
          set +e
          docker rm -f ${CONTAINER_NAME}
          set -e
        '''
      }
    }

    stage('Run Container') {
      steps {
        sh '''
          set -eux
          ENV_FILE_MOUNT="${HOST_ENV_FILE}"
          if [ ! -f "${ENV_FILE_MOUNT}" ]; then
            ENV_FILE_MOUNT="${HOST_APP_ROOT}/.env"
          fi

          STORAGE_SRC="${HOST_APP_ROOT}/storage"

          docker run -d \
            --name ${CONTAINER_NAME} \
            --restart unless-stopped \
            --network ${DOCKER_NETWORK} \
            -p ${APP_PORT}:80 \
            -e APP_URL=http://192.168.0.105:${APP_PORT} \
            -e DB_HOST=${DB_OVERRIDE_HOST} \
            -e DB_PORT=${DB_OVERRIDE_PORT} \
            -e DB_DATABASE=current_affairs \
            -e DB_USERNAME=ca_app \
            -e DB_PASSWORD=ca_secret_2026 \
            -v "${ENV_FILE_MOUNT}:/var/www/html/.env:ro" \
            -v "${STORAGE_SRC}:/var/www/html/storage" \
            ${IMAGE_NAME}:latest
        '''
      }
    }

    stage('Health Check') {
      steps {
        sh '''
          set -eux
          for i in $(seq 1 40); do
            code="$(curl -o /dev/null -s -w "%{http_code}" http://127.0.0.1:${APP_PORT}/up || echo 000)"
            if echo "$code" | grep -qE '^(200)$'; then
              break
            fi
            sleep 3
          done
          curl -fsS http://127.0.0.1:${APP_PORT}/up >/dev/null
          curl -fsS -o /dev/null -w "%{http_code}\\n" http://127.0.0.1:${APP_PORT}/admin/ || true
        '''
      }
    }
  }

  post {
    always {
      sh '''
        docker ps -a --filter "name=${CONTAINER_NAME}" || true
        ss -ltn | grep ${APP_PORT} || true
      '''
    }
  }
}
