#!/bin/bash

# Check if service 'app' is running
IS_RUNNING=$(docker compose ps --filter "status=running" --services | grep -w app)

if [ -z "$IS_RUNNING" ]; then
    echo -e "\033[91mERRO: O serviço 'app' não está rodando.\033[0m"
    echo -e "\033[93mPara executar este comando, o projeto precisa estar ativo."
    echo -e "Suba o ambiente primeiro usando:"
    echo -e "  make up"
    echo -e "ou"
    echo -e "  make dev\033[0m"
    exit 1
fi
