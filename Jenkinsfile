/*
** Variables.
*/
properties([buildDiscarder(logRotator(numToKeepStr: '50'))])
def serie = '20.10'
def maintenanceBranch = "${serie}.x"
if (env.BRANCH_NAME.startsWith('release-')) {
  env.BUILD = 'RELEASE'
} else if ((env.BRANCH_NAME == 'master') || (env.BRANCH_NAME == maintenanceBranch)) {
  env.BUILD = 'REFERENCE'
} else {
  env.BUILD = 'CI'
}

/*
** Pipeline code.
*/
stage('Source') {
  node {
    sh 'setup_centreon_build.sh'
    dir('centreon-agent-configuration') {
      checkout scm
    }
    sh "./centreon-build/jobs/agent-config/${serie}/agent-config-source.sh"
    source = readProperties file: 'source.properties'
    env.VERSION = "${source.VERSION}"
    env.RELEASE = "${source.RELEASE}"
    stash name: 'tar-sources', includes: "centreon-agent-configuration-${env.VERSION}.tar.gz"
    publishHTML([
      allowMissing: false,
      keepAll: true,
      reportDir: 'summary',
      reportFiles: 'index.html',
      reportName: 'Centreon Agent Configuration Build Artifacts',
      reportTitles: ''
    ])
    if ((env.BUILD == 'RELEASE') || (env.BUILD == 'REFERENCE')) {
      withSonarQubeEnv('SonarQube') {
        sh "./centreon-build/jobs/agent-config/${serie}/agent-config-analysis.sh"
      }
    }
  }
}

try {
  stage('Package') {
    parallel 'centos7': {
      node {
        sh 'setup_centreon_build.sh'
        unstash 'tar-sources'
        sh "./centreon-build/jobs/agent-config/${serie}/agent-config-package.sh centos7"
      }
    }
    if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
      error('Package stage failure.')
    }
  }

  if ((env.BUILD == 'RELEASE') || (env.BUILD == 'REFERENCE')) {
    stage('Delivery') {
      node {
        sh 'setup_centreon_build.sh'
        unstash 'tar-sources'
        sh "./centreon-build/jobs/agent-config/${serie}/agent-config-delivery.sh"
      }
      if ((currentBuild.result ?: 'SUCCESS') != 'SUCCESS') {
        error('Delivery stage failure.');
      }
    }
  }
}
finally {
  buildStatus = currentBuild.result ?: 'SUCCESS';
  if ((buildStatus != 'SUCCESS') && ((env.BUILD == 'RELEASE') || (env.BUILD == 'REFERENCE'))) {
    slackSend channel: '#monitoring-metrology', message: "@channel Centreon Agent Configuration build ${env.BUILD_NUMBER} of branch ${env.BRANCH_NAME} was broken by ${source.COMMITTER}. Please fix it ASAP."
  }
}
