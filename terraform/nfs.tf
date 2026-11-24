# =====================================================
# EC2 SERVIDOR NFS (sessoes e uploads compartilhados)
# =====================================================
# Fica na subnet privada, sem IP publico. A instalacao dos pacotes sai
# pela NAT instance (app_server), por isso depende dela.
# Administracao via SSM (mesmo padrao da EC2 do PostgreSQL).

resource "aws_instance" "nfs_server" {
  ami           = data.aws_ssm_parameter.ubuntu_2404_ami.value
  instance_type = var.nfs_instance_type

  subnet_id              = aws_subnet.private_subnet.id
  vpc_security_group_ids = [aws_security_group.nfs_sg.id]

  associate_public_ip_address = false
  private_ip                  = var.nfs_private_ip

  iam_instance_profile = aws_iam_instance_profile.ssm_profile.name

  root_block_device {
    volume_size = 10
    volume_type = "gp3"
    encrypted   = true
  }

  user_data = <<-EOF
    #!/bin/bash
    exec > /var/log/user-data.log 2>&1
    set -ex
    export DEBIAN_FRONTEND=noninteractive

    # Aguarda a saida pela NAT instance
    for i in $(seq 1 30); do
      apt-get update -y && break
      sleep 10
    done
    apt-get install -y nfs-kernel-server prometheus-node-exporter

    # uid/gid 82 = www-data da imagem alpine da aplicacao
    mkdir -p /srv/nfs/uploads /srv/nfs/sessions
    chown 82:82 /srv/nfs/uploads /srv/nfs/sessions
    chmod 0777 /srv/nfs/uploads /srv/nfs/sessions

    cat > /etc/exports <<'EXP'
    /srv/nfs           ${var.vpc_cidr}(fsid=0,rw,sync,no_subtree_check,no_root_squash)
    /srv/nfs/uploads   ${var.vpc_cidr}(rw,sync,no_subtree_check,no_root_squash)
    /srv/nfs/sessions  ${var.vpc_cidr}(rw,sync,no_subtree_check,no_root_squash)
    EXP

    # Somente NFSv4 (porta unica 2049)
    sed -i 's/^RPCNFSDARGS=.*/RPCNFSDARGS="-N 2 -N 3"/' /etc/default/nfs-kernel-server
    sed -i 's/^RPCMOUNTDOPTS=.*/RPCMOUNTDOPTS="--manage-gids -N 2 -N 3"/' /etc/default/nfs-kernel-server

    systemctl enable --now nfs-kernel-server
    exportfs -ra
    systemctl enable --now prometheus-node-exporter
  EOF

  user_data_replace_on_change = true

  depends_on = [
    aws_route_table_association.private_assoc,
    aws_instance.app_server,
    aws_iam_role_policy_attachment.ssm_policy
  ]

  # Evita recriar a instancia (e perder dados) quando a AMI do SSM for atualizada.
  lifecycle {
    ignore_changes = [ami]
  }

  tags = {
    Name    = "gci-nfs-server"
    Project = "GCI"
    Service = "Storage"
  }
}
