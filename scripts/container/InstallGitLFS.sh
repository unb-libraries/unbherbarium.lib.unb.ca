#!/usr/bin/env sh
curl -LO https://github.com/git-lfs/git-lfs/releases/download/v${GIT_LFS_VERSION}/git-lfs-linux-amd64-${GIT_LFS_VERSION}.tar.gz
tar xvzpf git-lfs-linux-amd64-${GIT_LFS_VERSION}.tar.gz
rm -rf git-lfs-linux-amd64-${GIT_LFS_VERSION}.tar.gz
cd git-lfs-${GIT_LFS_VERSION}
./install.sh
cd ..
rm -rf git-lfs-${GIT_LFS_VERSION}
