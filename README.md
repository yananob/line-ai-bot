# line-ai-bot

## Deployment

This application is deployed as Google Cloud Functions using GitHub Actions.

### Workflow

The deployment workflow is defined in `.github/workflows/deploy-gcp.yml`. It automatically deploys two functions upon pushes to the `main` branch:

1.  **`line-ai-bot`**: An HTTP-triggered function.
2.  **`line-ai-bot-trigger`**: An event-triggered function (Pub/Sub).

### Prerequisites

Before the workflow can successfully deploy, the following must be configured:

1.  **Workload Identity Federation**: Google Cloud needs to be configured to trust GitHub Actions. This involves setting up a Workload Identity Pool, a Provider, and a Service Account with appropriate permissions. Detailed instructions were provided during the initial setup of this workflow.
2.  **GitHub Secrets**: The workflow relies on several secrets for authentication and configuration:
    *   `GCP_PROJECT_ID`
    *   `GCP_WORKLOAD_IDENTITY_PROVIDER`
    *   `GCP_SERVICE_ACCOUNT_EMAIL`
    *   `COMMON_FIREBASE_BASE64`
    *   `GPT_BASE64`
    *   `CONFIG_JSON_BASE64` (Optional)
    Detailed instructions for setting up these secrets were provided during the initial setup.

Once these prerequisites are met, pushes to the `main` branch will automatically trigger a deployment. You can also manually trigger the deployment from the Actions tab in GitHub.
