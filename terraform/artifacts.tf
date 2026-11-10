data "aws_caller_identity" "current" {}

resource "aws_s3_bucket" "db_artifacts" {
  bucket = "gci-db-artifacts-${data.aws_caller_identity.current.account_id}-${var.aws_region}"

  tags = {
    Name    = "gci-db-artifacts"
    Project = "GCI"
  }
}

resource "aws_s3_bucket_versioning" "db_artifacts" {
  bucket = aws_s3_bucket.db_artifacts.id

  versioning_configuration {
    status = "Enabled"
  }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "db_artifacts" {
  bucket = aws_s3_bucket.db_artifacts.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
  }
}

resource "aws_s3_bucket_public_access_block" "db_artifacts" {
  bucket = aws_s3_bucket.db_artifacts.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

# =====================================================
# UPLOAD DO DUMP E DOS SCRIPTS DE INICIALIZAÇÃO
# (o runner do GitHub Actions envia os arquivos do repo;
#  a EC2 privada baixa pelo VPC Endpoint de S3)
# =====================================================

locals {
  db_init_files = {
    "gci.sql"           = "${path.module}/../database/gci.sql"
    "01-create-role.sh" = "${path.module}/../database/init/01-create-role.sh"
    "02-restore.sh"     = "${path.module}/../database/init/02-restore.sh"
  }
}

resource "aws_s3_object" "db_init" {
  for_each = local.db_init_files

  bucket = aws_s3_bucket.db_artifacts.id
  key    = "postgres/${each.key}"
  source = each.value
  etag   = filemd5(each.value)

  depends_on = [
    aws_s3_bucket_server_side_encryption_configuration.db_artifacts
  ]
}

# =====================================================
# PERMISSÃO DA EC2 PARA LER OS ARQUIVOS DO S3
# =====================================================

resource "aws_iam_role_policy" "postgres_s3_read" {
  name = "gci-postgres-s3-read"
  role = aws_iam_role.ssm_role.id

  policy = jsonencode({
    Version = "2012-10-17"

    Statement = [
      {
        Effect = "Allow"

        Action = [
          "s3:GetObject"
        ]

        Resource = [
          "${aws_s3_bucket.db_artifacts.arn}/postgres/*"
        ]
      }
    ]
  })
}