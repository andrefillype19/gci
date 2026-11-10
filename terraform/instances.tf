# =====================================================
# IAM ROLE PARA SSM
# =====================================================

resource "aws_iam_role" "ssm_role" {
  name = "gci-ec2-ssm-role"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"

    Statement = [
      {
        Action = "sts:AssumeRole"
        Effect = "Allow"

        Principal = {
          Service = "ec2.amazonaws.com"
        }
      }
    ]
  })
}

resource "aws_iam_role_policy_attachment" "ssm_policy" {
  role       = aws_iam_role.ssm_role.name
  policy_arn = "arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore"
}

resource "aws_iam_instance_profile" "ssm_profile" {
  name = "gci-ssm-instance-profile"
  role = aws_iam_role.ssm_role.name
}

# =====================================================
# EC2 POSTGRESQL 11
# =====================================================

resource "aws_instance" "postgres_db" {
  ami           = data.aws_ssm_parameter.ubuntu_2404_ami.value
  instance_type = var.db_instance_type

  subnet_id              = aws_subnet.private_subnet.id
  vpc_security_group_ids = [aws_security_group.db_sg.id]

  associate_public_ip_address = false

  # IP fixo para que o DB_HOST do Secret do k3s não mude a cada recriação
  private_ip = var.db_private_ip

  iam_instance_profile = aws_iam_instance_profile.ssm_profile.name

  # Não utiliza key_name.
  # A administração será feita pelo AWS SSM.

  root_block_device {
    volume_size = 15
    volume_type = "gp3"
    encrypted   = true
  }

  # Bootstrap inicial do PostgreSQL
  # (instala o PG 11, baixa gci.sql + scripts do S3 e executa o restore)
  user_data = templatefile(
    "${path.module}/scripts/postgres/bootstrap.sh",
    {
      vpc_cidr         = var.vpc_cidr
      aws_region       = var.aws_region
      artifacts_bucket = aws_s3_bucket.db_artifacts.bucket
      db_name          = var.db_name
      db_password      = var.db_password
    }
  )

  # user_data só roda no primeiro boot: se o script mudar,
  # a instância precisa ser recriada para executá-lo de novo.
  user_data_replace_on_change = true

  depends_on = [
    aws_route_table_association.private_assoc,
    aws_nat_gateway.gci_nat,
    aws_vpc_endpoint.s3,
    aws_iam_role_policy_attachment.ssm_policy,
    aws_iam_role_policy.postgres_s3_read,
    aws_s3_object.db_init
  ]

  tags = {
    Name    = "gci-postgres-db"
    Project = "GCI"
    Service = "Database"
  }
}

# =====================================================
# EC2 APLICAÇÃO / K3S
# =====================================================

resource "aws_instance" "app_server" {
  ami           = data.aws_ssm_parameter.ubuntu_2404_ami.value
  instance_type = var.app_instance_type

  subnet_id              = aws_subnet.public_subnet.id
  vpc_security_group_ids = [aws_security_group.app_sg.id]

  iam_instance_profile = aws_iam_instance_profile.ssm_profile.name

  key_name = var.key_name

  root_block_device {
    volume_size = 20
    volume_type = "gp3"
    encrypted   = true
  }

  user_data = <<-EOF
    #!/bin/bash

    exec > /var/log/user-data.log 2>&1
    set -ex

    export DEBIAN_FRONTEND=noninteractive

    apt-get update -y
    apt-get install -y curl git

    # Instalação do k3s
    curl -sfL https://get.k3s.io | \
      K3S_NODE_NAME="gci-app-server" sh -

    # Configurações do kubectl
    chmod 644 /etc/rancher/k3s/k3s.yaml

    ln -sf /usr/local/bin/k3s /usr/bin/k3s
    ln -sf /usr/local/bin/kubectl /usr/bin/kubectl

    # Aguarda o Kubernetes iniciar
    for i in $(seq 1 30); do
      if kubectl get nodes; then
        break
      fi
      sleep 10
    done

    kubectl create namespace app \
      --dry-run=client -o yaml | kubectl apply -f -

  EOF

  tags = {
    Name    = "gci-app-server"
    Project = "GCI"
    Service = "Application"
  }
}